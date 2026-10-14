'use client';

import { useState } from 'react';
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Pencil, Plus, Scale, Trash2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, DataTable, DateInput, Drawer, ErrorState, FormField, FormGrid, IconButton, Input, MenuItem, MenuSeparator, Select, SkeletonCard, StatusBadge, Switch, Textarea,
  paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { formatDate } from '@/lib/format';
import { compareDecimals } from '@/features/inventory/decimal';
import { trimDecimal } from '@/features/finance/receivables/money';
import { TAX_PERIODS, taxTables, useCompanyCurrency, type TaxTable } from '../api';
import { MONEY_RE, RATE_RE, todayIso } from '../components';
import crud from '@/components/crud/crud.module.scss';
import styles from '../payroll.module.scss';

export function ComplianceNote() {
  return (
    <Alert variant="warning" icon={<Scale size={18} />} title="Tax rules are configurable — compliance is your responsibility">
      No country’s tax law is built in. Income tax is calculated only from the tables you enter here (progressive brackets on taxable income),
      and allowances, credits, caps and statutory contributions must be modelled with tax tables and pay components. Review your configuration
      with a qualified tax adviser; the company remains responsible for statutory compliance.
    </Alert>
  );
}

const bracketSchema = z.object({
  from_amount: z.string().trim(),
  to_amount: z.string().trim(),
  rate: z.string().trim(),
  fixed_amount: z.string().trim(),
});
type Bracket = z.infer<typeof bracketSchema>;

/**
 * Brackets must be listed in ascending order, each one starting exactly where
 * the previous one ends (contiguous), and only the last may be open-ended.
 * Issues are attached to the offending row's field.
 */
function validateBrackets(rows: Bracket[], ctx: z.RefinementCtx) {
  if (rows.length === 0) {
    ctx.addIssue({ code: 'custom', path: ['brackets'], message: 'Add at least one bracket' });
    return;
  }
  rows.forEach((b, i) => {
    const at = (field: keyof Bracket, message: string) => ctx.addIssue({ code: 'custom', path: ['brackets', i, field], message });
    if (!MONEY_RE.test(b.from_amount)) at('from_amount', 'Required, non-negative');
    if (b.to_amount !== '' && !MONEY_RE.test(b.to_amount)) at('to_amount', 'Non-negative amount or empty');
    if (!RATE_RE.test(b.rate) || Number(b.rate) > 100) at('rate', '0–100 %');
    if (b.fixed_amount !== '' && !MONEY_RE.test(b.fixed_amount)) at('fixed_amount', 'Non-negative amount');
    const last = i === rows.length - 1;
    if (b.to_amount === '' && !last) at('to_amount', 'Only the last bracket may be open-ended');
    if (b.to_amount !== '' && MONEY_RE.test(b.to_amount) && MONEY_RE.test(b.from_amount) && compareDecimals(b.to_amount, b.from_amount) <= 0) {
      at('to_amount', 'Must be greater than “from”');
    }
    if (i > 0) {
      const prev = rows[i - 1]!;
      if (prev.to_amount !== '' && MONEY_RE.test(b.from_amount) && MONEY_RE.test(prev.to_amount) && compareDecimals(b.from_amount, prev.to_amount) !== 0) {
        at('from_amount', compareDecimals(b.from_amount, prev.to_amount) < 0
          ? `Overlaps the previous bracket — should start at ${trimDecimal(prev.to_amount)}`
          : `Gap after the previous bracket — should start at ${trimDecimal(prev.to_amount)}`);
      }
    }
  });
}

const schema = z
  .object({
    name: z.string().trim().min(1, 'Name is required').max(100),
    effective_from: z.string().min(1, 'Effective date is required'),
    period: z.enum(['monthly', 'annual']),
    is_active: z.boolean(),
    notes: z.string().max(1000),
    brackets: z.array(bracketSchema).max(50, 'At most 50 brackets'),
  })
  .superRefine((v, ctx) => validateBrackets(v.brackets, ctx));
type Values = z.infer<typeof schema>;

const blankBracket = (from = ''): Bracket => ({ from_amount: from, to_amount: '', rate: '', fixed_amount: '0' });

function TaxTableForm({ row, onClose }: { row: TaxTable | null; onClose: () => void }) {
  const currency = useCompanyCurrency();
  const [formError, setFormError] = useState<string | null>(null);
  const create = taxTables.useCreate({ onSuccess: onClose });
  const update = taxTables.useUpdate({ onSuccess: onClose });
  const { register, handleSubmit, control, setError, getValues, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: row?.name ?? '',
      effective_from: row?.effective_from ?? todayIso(),
      period: row?.period ?? 'annual',
      is_active: row?.is_active ?? true,
      notes: row?.notes ?? '',
      brackets: row?.brackets?.length
        ? row.brackets.map((b) => ({ from_amount: trimDecimal(b.from_amount) || '0', to_amount: b.to_amount === null ? '' : trimDecimal(b.to_amount) || '0', rate: trimDecimal(b.rate) || '0', fixed_amount: trimDecimal(b.fixed_amount) || '0' }))
        : [blankBracket('0')],
    },
  });
  const { fields, append, remove } = useFieldArray({ control, name: 'brackets' });
  const period = useWatch({ control, name: 'period' });
  const e = formState.errors;
  const rowErr = (i: number, f: keyof Bracket) => e.brackets?.[i]?.[f]?.message;
  const listError = e.brackets?.message ?? e.brackets?.root?.message;

  const addBracket = () => {
    const rows = getValues('brackets');
    const last = rows[rows.length - 1];
    append(blankBracket(last?.to_amount ?? ''));
  };

  return (
    <Drawer open onClose={onClose} title={row ? `Edit ${row.name}` : 'New tax table'} size="xl"
      description="Progressive income tax brackets applied to each payslip's taxable income."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="tax-table-form" loading={formState.isSubmitting}>{row ? 'Save changes' : 'Create tax table'}</Button>
        </>
      }>
      <form id="tax-table-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = {
          name: v.name.trim(),
          effective_from: v.effective_from,
          period: v.period,
          is_active: v.is_active,
          notes: v.notes.trim() || null,
          brackets: v.brackets.map((b) => ({
            from_amount: b.from_amount,
            to_amount: b.to_amount === '' ? null : b.to_amount,
            rate: b.rate,
            fixed_amount: b.fixed_amount === '' ? '0' : b.fixed_amount,
          })),
        };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          const x = err as ApiError;
          const left = applyServerErrors(x, setError as never, ['name', 'effective_from', 'period', 'is_active', 'notes', 'brackets']);
          setFormError(x.status === 422 ? left : (left ?? x.message));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <div style={{ marginBottom: 16 }}><ComplianceNote /></div>
        <FormGrid columns={3}>
          <FormField label="Name" required error={e.name?.message}><Input maxLength={100} {...register('name')} autoFocus={!row} /></FormField>
          <Controller control={control} name="effective_from" render={({ field }) => (
            <FormField label="Effective from" required error={e.effective_from?.message} hint="The latest active table effective on the period end is used.">
              <DateInput value={field.value} onValueChange={(v) => field.onChange(v ?? '')} />
            </FormField>
          )} />
          <FormField label="Brackets are expressed in" required error={e.period?.message}><Select {...register('period')} options={TAX_PERIODS} /></FormField>
          <FormField label="Notes" error={e.notes?.message} span="full"><Textarea rows={2} maxLength={1000} {...register('notes')} placeholder="Source of the rates, assumptions, review date…" /></FormField>
        </FormGrid>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />} />
        </div>

        <h4 className={styles.sectionTitle}>Brackets ({period === 'annual' ? 'annual' : 'monthly'} taxable income, {currency})</h4>
        <p className={styles.hint} style={{ marginBottom: 8 }}>
          For each bracket reached: tax += fixed amount + rate % × (income within the bracket). List brackets from lowest to highest; each starts where the previous ends. Leave “to” empty on the last bracket for no upper limit.
          {period === 'annual' && ' Monthly taxable income is annualised (×12) and the annual tax divided by 12.'}
        </p>
        <div className={styles.scroll}>
          <table className={styles.gridTable} style={{ minWidth: 640 }}>
            <thead>
              <tr><th>#</th><th>From</th><th>To</th><th>Rate %</th><th>Fixed amount</th><th style={{ width: 44 }} aria-label="Remove" /></tr>
            </thead>
            <tbody>
              {fields.map((f, i) => (
                <tr key={f.id}>
                  <td className={styles.hint} style={{ paddingTop: 14 }}>{i + 1}</td>
                  {(['from_amount', 'to_amount', 'rate', 'fixed_amount'] as const).map((k) => (
                    <td key={k}>
                      <Input aria-label={`Bracket ${i + 1} ${k.replace('_', ' ')}`} inputMode="decimal" invalid={!!rowErr(i, k)}
                        placeholder={k === 'to_amount' ? (i === fields.length - 1 ? 'No limit' : '') : '0'} {...register(`brackets.${i}.${k}`)} />
                      {rowErr(i, k) && <small className={styles.fieldError}>{rowErr(i, k)}</small>}
                    </td>
                  ))}
                  <td><IconButton icon={<Trash2 size={16} />} size="sm" aria-label={`Remove bracket ${i + 1}`} disabled={fields.length <= 1} onClick={() => remove(i)} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div style={{ marginTop: 8 }}>
          <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={addBracket} disabled={fields.length >= 50}>Add bracket</Button>
        </div>
        {listError && <Alert variant="danger" style={{ marginTop: 12 }}>{listError}</Alert>}
      </form>
    </Drawer>
  );
}

function EditTaxTable({ id, onClose }: { id: number; onClose: () => void }) {
  const q = taxTables.useOne(id);
  if (q.error) return <Drawer open onClose={onClose} title="Tax table"><ErrorState error={q.error} onRetry={() => void q.refetch()} /></Drawer>;
  if (!q.data) return <Drawer open onClose={onClose} title="Tax table"><SkeletonCard /></Drawer>;
  return <TaxTableForm row={q.data} onClose={onClose} />;
}

export function TaxTablesTab({ canManage }: { canManage: boolean }) {
  const [page, setPage] = useState(1);
  const q = taxTables.useList({ page, per_page: 50, sort: '-effective_from' });
  const del = taxTables.useDelete();
  const [editing, setEditing] = useState<number | 'new' | null>(null);
  const [deleting, setDeleting] = useState<TaxTable | null>(null);
  const meta = q.data?.meta?.pagination;

  const columns: DataTableColumn<TaxTable>[] = [
    { id: 'name', header: 'Name', enableHiding: false, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.name}</strong><span>{row.original.notes ?? ''}</span></div> },
    { id: 'effective_from', header: 'Effective from', cell: ({ row }) => formatDate(row.original.effective_from) },
    { id: 'period', header: 'Income basis', cell: ({ row }) => (row.original.period === 'annual' ? 'Annual' : 'Monthly') },
    { id: 'brackets', header: 'Brackets', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.brackets_count ?? '—' },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <div className={styles.stack}>
      <ComplianceNote />
      <div className={styles.between}>
        <span className={styles.hint}>Without an active tax table, payslips are calculated with zero tax and flagged “No tax table”.</span>
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New tax table</Button>}
      </div>
      <DataTable<TaxTable>
        aria-label="Tax tables"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
        onRowClick={canManage ? (row) => setEditing(row.id) : undefined}
        rowActions={canManage ? (row) => (
          <>
            <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(row.id)}>Edit</MenuItem>
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(row)}>Delete</MenuItem>
          </>
        ) : undefined}
        pagination={meta && meta.last_page > 1 ? { ...paginationFromMeta(meta), onPageChange: setPage } : undefined}
        emptyState={<div style={{ padding: 24 }} className={styles.hint}>No tax tables yet.</div>}
      />
      {editing === 'new' && <TaxTableForm key="new" row={null} onClose={() => setEditing(null)} />}
      {typeof editing === 'number' && <EditTaxTable key={editing} id={editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'tax table'}?`}
        description="Already calculated payslips keep their tax; future calculations will no longer use this table."
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </div>
  );
}
