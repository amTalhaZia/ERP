'use client';

import { useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, DataTable, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, SearchInput, Select, StatusBadge, Switch, Textarea,
  paginationFromMeta, type DataTableColumn,
} from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { applyServerErrors } from '@/lib/crud/resource';
import { trimDecimal } from '@/features/finance/receivables/money';
import {
  CALCULATIONS, KINDS, RESERVED_CODES, calcLabel, kindLabel, payComponents, useCompanyCurrency, useGlAccountOptions,
  type CalculationMethod, type ComponentKind, type PayComponent,
} from '../api';
import { MONEY_RE, RATE_RE, componentValue } from '../components';
import crud from '@/components/crud/crud.module.scss';
import styles from '../payroll.module.scss';

const schema = z
  .object({
    code: z.string().trim().min(1, 'Code is required').max(30).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only')
      .refine((v) => !RESERVED_CODES.includes(v.toUpperCase()), 'BASE and TAX are reserved'),
    name: z.string().trim().min(1, 'Name is required').max(100),
    description: z.string().max(500),
    kind: z.enum(['earning', 'deduction', 'employer_contribution']),
    calculation: z.enum(['fixed', 'percent_of_base', 'percent_of_gross']),
    default_amount: z.string().trim(),
    default_rate: z.string().trim(),
    is_taxable: z.boolean(),
    affects_gross: z.boolean(),
    reduces_taxable: z.boolean(),
    gl_account_code: z.string(),
    sort_order: z.string().refine((v) => /^\d{1,5}$/.test(v) && Number(v) <= 65535, '0–65535'),
    is_active: z.boolean(),
  })
  .superRefine((v, ctx) => {
    if (v.kind === 'earning' && v.calculation === 'percent_of_gross') {
      ctx.addIssue({ code: 'custom', path: ['calculation'], message: 'Earnings cannot be a percentage of gross' });
    }
    if (v.calculation === 'fixed' && !MONEY_RE.test(v.default_amount || '0')) {
      ctx.addIssue({ code: 'custom', path: ['default_amount'], message: 'A non-negative amount (max 4 decimals)' });
    }
    if (v.calculation !== 'fixed' && !(RATE_RE.test(v.default_rate || '0') && Number(v.default_rate || 0) <= 1000)) {
      ctx.addIssue({ code: 'custom', path: ['default_rate'], message: '0–1000 %, max 4 decimals' });
    }
  });
type Values = z.infer<typeof schema>;
const FIELDS = ['code', 'name', 'description', 'kind', 'calculation', 'default_amount', 'default_rate', 'is_taxable', 'affects_gross', 'reduces_taxable', 'gl_account_code', 'sort_order', 'is_active'];

function PayComponentForm({ row, onClose }: { row: PayComponent | null; onClose: () => void }) {
  const currency = useCompanyCurrency();
  const accounts = useGlAccountOptions();
  const [formError, setFormError] = useState<string | null>(null);
  const create = payComponents.useCreate({ onSuccess: onClose });
  const update = payComponents.useUpdate({ onSuccess: onClose });
  const { register, handleSubmit, control, setError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      code: row?.code ?? '',
      name: row?.name ?? '',
      description: row?.description ?? '',
      kind: row?.kind ?? 'earning',
      calculation: row?.calculation ?? 'fixed',
      default_amount: row ? trimDecimal(row.default_amount) || '0' : '0',
      default_rate: row ? trimDecimal(row.default_rate) || '0' : '0',
      is_taxable: row?.is_taxable ?? true,
      affects_gross: row?.affects_gross ?? true,
      reduces_taxable: row?.reduces_taxable ?? false,
      gl_account_code: row?.gl_account_code ?? '',
      sort_order: String(row?.sort_order ?? 0),
      is_active: row?.is_active ?? true,
    },
  });
  const kind = useWatch({ control, name: 'kind' });
  const calculation = useWatch({ control, name: 'calculation' });
  const e = formState.errors;
  const fixed = calculation === 'fixed';
  const accountOptions = row?.gl_account_code && !accounts.options.some((o) => o.value === row.gl_account_code)
    ? [{ value: row.gl_account_code, label: row.gl_account_code }, ...accounts.options]
    : accounts.options;

  return (
    <Drawer open onClose={onClose} title={row ? `Edit ${row.code}` : 'New pay component'} size="md"
      description="Allowances, deductions and employer contributions added to payslips through salary structures."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="pay-component-form" loading={formState.isSubmitting}>{row ? 'Save changes' : 'Create component'}</Button>
        </>
      }>
      <form id="pay-component-form" noValidate onSubmit={handleSubmit(async (v) => {
        setFormError(null);
        const body = {
          ...v,
          code: v.code.trim().toUpperCase(),
          description: v.description.trim() || null,
          default_amount: v.default_amount || '0',
          default_rate: v.default_rate || '0',
          gl_account_code: v.gl_account_code || null,
          sort_order: Number(v.sort_order),
          // Only earnings are taxable / affect gross; only deductions reduce taxable income.
          is_taxable: v.kind === 'earning' ? v.is_taxable : false,
          affects_gross: v.kind === 'earning' ? v.affects_gross : true,
          reduces_taxable: v.kind === 'deduction' ? v.reduces_taxable : false,
        };
        try {
          if (row) await update.mutateAsync({ id: row.id, body });
          else await create.mutateAsync(body);
        } catch (err) {
          const x = err as ApiError;
          const left = applyServerErrors(x, setError as never, FIELDS);
          setFormError(x.status === 422 ? left : (left ?? x.message));
        }
      })}>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Code" required error={e.code?.message} hint="e.g. HRA, PF, MEAL"><Input {...register('code')} style={{ textTransform: 'uppercase' }} autoFocus={!row} /></FormField>
          <FormField label="Name" required error={e.name?.message}><Input maxLength={100} {...register('name')} /></FormField>
          <FormField label="Kind" required error={e.kind?.message}><Select {...register('kind')} options={KINDS} /></FormField>
          <FormField label="Calculation" required error={e.calculation?.message}>
            <Select {...register('calculation')} options={CALCULATIONS.map((c) => ({ ...c, disabled: kind === 'earning' && c.value === 'percent_of_gross' }))} />
          </FormField>
          {fixed ? (
            <FormField label={`Default amount (${currency})`} error={e.default_amount?.message} hint="Per month; can be overridden per structure or employee.">
              <Input inputMode="decimal" {...register('default_amount')} />
            </FormField>
          ) : (
            <FormField label="Default rate %" error={e.default_rate?.message} hint={calculation === 'percent_of_gross' ? 'Percent of gross pay, e.g. 7.5' : 'Percent of the (prorated) base salary, e.g. 10'}>
              <Input inputMode="decimal" {...register('default_rate')} />
            </FormField>
          )}
          <FormField label="Sort order" error={e.sort_order?.message}><Input inputMode="numeric" {...register('sort_order')} /></FormField>
          <FormField label="GL account" error={e.gl_account_code?.message} span="full"
            hint={`Leave empty to use the default for the kind (${kind === 'earning' ? 'salaries expense' : kind === 'deduction' ? 'accrued liabilities' : 'payroll taxes expense'})${row ? ` — currently ${row.effective_gl_account_code}` : ''}.`}>
            <Select placeholder="Default account" {...register('gl_account_code')} options={accountOptions} />
          </FormField>
          <FormField label="Description" error={e.description?.message} span="full"><Textarea rows={2} maxLength={500} {...register('description')} /></FormField>
        </FormGrid>
        <div style={{ display: 'grid', gap: 12, marginTop: 16 }}>
          {kind === 'earning' && (
            <>
              <Controller control={control} name="is_taxable" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Taxable" description="Included in taxable income." />} />
              <Controller control={control} name="affects_gross" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Part of gross pay" description="Turn off for reimbursements: paid in net but excluded from gross and % of gross calculations." />} />
            </>
          )}
          {kind === 'deduction' && (
            <Controller control={control} name="reduces_taxable" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Pre-tax deduction" description="Reduces taxable income (e.g. pension contributions where allowed)." />} />
          )}
          {kind === 'employer_contribution' && <p className={styles.hint}>Employer contributions are a company cost on top of gross; they are not deducted from the employee’s pay.</p>}
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive components are skipped in new calculations and hidden from pickers." />} />
        </div>
      </form>
    </Drawer>
  );
}

export function PayComponentsTab({ canManage }: { canManage: boolean }) {
  const currency = useCompanyCurrency();
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [kind, setKind] = useState('');
  const q = payComponents.useList({ page, per_page: 50, sort: 'sort_order', search: search || undefined, 'filter[kind]': kind || undefined });
  const del = payComponents.useDelete();
  const [editing, setEditing] = useState<PayComponent | 'new' | null>(null);
  const [deleting, setDeleting] = useState<PayComponent | null>(null);
  const meta = q.data?.meta?.pagination;

  const columns: DataTableColumn<PayComponent>[] = [
    { id: 'code', header: 'Code', cell: ({ row }) => <span className={crud.mono}>{row.original.code}</span> },
    { id: 'name', header: 'Name', enableHiding: false, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.name}</strong><span>{row.original.description ?? ''}</span></div> },
    { id: 'kind', header: 'Kind', cell: ({ row }) => <Badge size="sm" variant={row.original.kind === 'earning' ? 'success' : row.original.kind === 'deduction' ? 'warning' : 'info'}>{kindLabel(row.original.kind as ComponentKind)}</Badge> },
    { id: 'calculation', header: 'Calculation', cell: ({ row }) => calcLabel(row.original.calculation as CalculationMethod) },
    { id: 'default', header: 'Default', meta: { numeric: true, align: 'right' }, cell: ({ row }) => componentValue(row.original, currency) },
    {
      id: 'flags', header: 'Tax treatment',
      cell: ({ row }) => {
        const c = row.original;
        const flags = [c.kind === 'earning' && (c.is_taxable ? 'Taxable' : 'Non-taxable'), c.kind === 'earning' && !c.affects_gross && 'Excluded from gross', c.reduces_taxable && 'Pre-tax'].filter(Boolean);
        return flags.length ? flags.join(' · ') : <span className={crud.muted}>—</span>;
      },
    },
    { id: 'gl', header: 'GL', cell: ({ row }) => <span className={crud.mono}>{row.original.effective_gl_account_code}</span> },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <div className={styles.row}>
          <SearchInput placeholder="Search code or name" defaultValue={search} onDebouncedChange={(v) => { setSearch(v); setPage(1); }} aria-label="Search pay components" />
          <Select aria-label="Kind" placeholder="All kinds" value={kind} onChange={(e) => { setKind(e.target.value); setPage(1); }} options={KINDS} />
        </div>
        {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New component</Button>}
      </div>
      <DataTable<PayComponent>
        aria-label="Pay components"
        columns={columns}
        data={q.data?.data ?? []}
        isLoading={q.isLoading}
        error={q.error}
        onRetry={() => void q.refetch()}
        onRowClick={canManage ? (row) => setEditing(row) : undefined}
        rowActions={canManage ? (row) => (
          <>
            <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(row)}>Edit</MenuItem>
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(row)}>Delete</MenuItem>
          </>
        ) : undefined}
        pagination={meta && meta.last_page > 1 ? { ...paginationFromMeta(meta), onPageChange: setPage } : undefined}
        emptyState={<div style={{ padding: 24 }} className={styles.hint}>{search || kind ? 'No components match.' : 'No pay components yet. Basic salary and income tax are built in; add allowances, deductions and employer contributions here.'}</div>}
      />
      {editing !== null && (
        <PayComponentForm key={editing === 'new' ? 'new' : editing.id} row={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.code ?? 'component'}?`}
        description="Components used by a salary structure or employee salary cannot be deleted — deactivate them instead."
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
