'use client';

import { Suspense, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch, type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { taxRatesFull, useAccountOptions, type TaxRateFull, type TaxRateInput, type TaxRateType } from '@/features/finance/receivables/api';
import { trimDecimal } from '@/features/finance/receivables/money';
import crud from '@/components/crud/crud.module.scss';

const TYPES: { value: TaxRateType; label: string }[] = [
  { value: 'sales', label: 'Sales (output tax)' },
  { value: 'purchase', label: 'Purchase (input tax)' },
  { value: 'both', label: 'Sales & purchase' },
];

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(100),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, digits, dashes and underscores only'),
  rate: z.string().trim().regex(/^\d{1,4}(\.\d{1,4})?$/, 'A percentage such as 17 or 7.5 (max 4 decimals)').refine((v) => Number(v) <= 1000, 'At most 1000%'),
  type: z.enum(['sales', 'purchase', 'both']),
  account_id: z.string(),
  is_compound: z.boolean(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

function TaxRateForm({ rate, onClose }: { rate: TaxRateFull | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const accounts = useAccountOptions('liability,asset');
  const create = taxRatesFull.useCreate({ onSuccess: onClose });
  const update = taxRatesFull.useUpdate({ onSuccess: onClose });
  const { register, handleSubmit, control, setError, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      name: rate?.name ?? '',
      code: rate?.code ?? '',
      rate: rate ? trimDecimal(rate.rate) || '0' : '',
      type: rate?.type ?? 'both',
      account_id: rate?.account_id ? String(rate.account_id) : '',
      is_compound: rate?.is_compound ?? false,
      is_active: rate?.is_active ?? true,
    },
  });

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: TaxRateInput = { ...v, account_id: v.account_id ? Number(v.account_id) : null };
    try {
      if (rate) await update.mutateAsync({ id: rate.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open
      onClose={onClose}
      title={rate ? `${rate.code} · ${rate.name}` : 'New tax rate'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="tax-form" loading={formState.isSubmitting}>{rate ? 'Save' : 'Create'}</Button>
        </>
      }
    >
      <form id="tax-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} placeholder="e.g. Standard VAT" /></FormField>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} placeholder="VAT17" /></FormField>
          <FormField label="Rate" required error={e.rate?.message}><Input {...register('rate')} inputMode="decimal" trailing="%" /></FormField>
          <FormField label="Applies to" required error={e.type?.message}><Select {...register('type')} options={TYPES} /></FormField>
          <FormField label="Tax GL account" span="full" error={e.account_id?.message} hint="Where collected / paid tax is posted. Leave empty to use the system tax account.">
            <Select {...register('account_id')} placeholder="System default" options={accounts.options} />
          </FormField>
        </FormGrid>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, marginTop: 16 }}>
          <Controller control={control} name="is_compound" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Compound" description="Calculated on top of other taxes." />} />
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive rates can't be used on new documents." />} />
        </div>
      </form>
    </Drawer>
  );
}

function TaxInner() {
  const can = useCan();
  const list = useListState({ sort: 'code' });
  const query = taxRatesFull.useList(list.apiParams);
  const del = taxRatesFull.useDelete();
  const [editing, setEditing] = useState<TaxRateFull | 'new' | null>(null);
  const [deleting, setDeleting] = useState<TaxRateFull | null>(null);
  const canManage = can('tax.manage');

  const columns: DataTableColumn<TaxRateFull>[] = [
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.code}</span> },
    { id: 'name', accessorKey: 'name', header: 'Name', enableSorting: true, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.name}</strong>{row.original.is_compound && <span>Compound</span>}</div> },
    { id: 'rate', accessorKey: 'rate', header: 'Rate', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => `${trimDecimal(row.original.rate) || '0'}%` },
    { id: 'type', header: 'Applies to', cell: ({ row }) => <Badge size="sm" variant={row.original.type === 'sales' ? 'success' : row.original.type === 'purchase' ? 'info' : 'neutral'}>{row.original.type === 'both' ? 'Sales & purchase' : row.original.type === 'sales' ? 'Sales' : 'Purchase'}</Badge> },
    { id: 'account', header: 'GL account', cell: ({ row }) => (row.original.account ? <><span className={crud.mono}>{row.original.account.code}</span> {row.original.account.name}</> : <span className={crud.muted}>System default</span>) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<TaxRateFull>
        title="Tax rates"
        description="Rates applied to invoice, bill and expense lines."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New tax rate</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name or code"
        filters={
          <>
            <Select aria-label="Applies to" placeholder="Any type" value={list.state.filters.type ?? ''} onChange={(ev) => list.update({ filters: { type: ev.target.value || undefined } })} options={TYPES} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(ev) => list.update({ filters: { is_active: ev.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
          </>
        }
        emptyTitle="No tax rates"
        emptyAction={canManage ? <Button size="sm" onClick={() => setEditing('new')}>New tax rate</Button> : undefined}
        onRowClick={canManage ? (t) => setEditing(t) : undefined}
        rowActions={
          canManage
            ? (t) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(t)}>Edit</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(t)}>Delete</MenuItem>
                </>
              )
            : undefined
        }
      />
      {canManage && editing !== null && <TaxRateForm key={editing === 'new' ? 'new' : editing.id} rate={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete tax rate ${deleting?.code ?? ''}?`}
        description="Rates already used in postings can't be deleted — deactivate them instead."
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function TaxPage() {
  return (
    <Suspense>
      <TaxInner />
    </Suspense>
  );
}
