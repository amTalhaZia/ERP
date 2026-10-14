'use client';

import { Suspense, useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { BookOpen, Lock, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch, Textarea,
  type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { accounts, type Account, type AccountType } from '@/features/finance/api';
import styles from '@/components/crud/crud.module.scss';

const TYPES: { value: AccountType; label: string }[] = [
  { value: 'asset', label: 'Asset' },
  { value: 'liability', label: 'Liability' },
  { value: 'equity', label: 'Equity' },
  { value: 'revenue', label: 'Revenue' },
  { value: 'expense', label: 'Expense' },
];
const SUBTYPES: Record<AccountType, string[]> = {
  asset: ['current_asset', 'cash', 'bank', 'receivable', 'inventory', 'prepaid', 'fixed_asset', 'accumulated_depreciation', 'other_asset'],
  liability: ['current_liability', 'payable', 'tax', 'accrued', 'long_term_liability'],
  equity: ['share_capital', 'retained_earnings', 'drawings', 'other_equity'],
  revenue: ['sales_revenue', 'service_revenue', 'other_income'],
  expense: ['cost_of_sales', 'operating_expense', 'payroll', 'depreciation', 'finance_cost', 'tax_expense', 'other_expense'],
};
const TYPE_VARIANT: Record<AccountType, 'info' | 'warning' | 'primary' | 'success' | 'danger'> = {
  asset: 'info', liability: 'warning', equity: 'primary', revenue: 'success', expense: 'danger',
};

const schema = z.object({
  code: z.string().trim().min(1, 'Code is required').max(20),
  name: z.string().trim().min(1, 'Name is required').max(150),
  type: z.enum(['asset', 'liability', 'equity', 'revenue', 'expense']),
  subtype: z.string().optional(),
  parent_id: z.string().optional(),
  description: z.string().max(500).optional(),
  is_bank: z.boolean(),
  is_cash: z.boolean(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

function AccountForm({ open, account, onClose }: { open: boolean; account: Account | null; onClose: () => void }) {
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, reset, watch, control, setError, formState } = useForm<Values>({ resolver: zodResolver(schema) });
  const create = accounts.useCreate({ onSuccess: onClose });
  const update = accounts.useUpdate({ onSuccess: onClose });
  const parents = accounts.useList({ per_page: 100, sort: 'code', 'filter[is_active]': 'true' }, { enabled: open });
  const type = watch('type');

  useEffect(() => {
    if (!open) return;
    reset({
      code: account?.code ?? '',
      name: account?.name ?? '',
      type: account?.type ?? 'expense',
      subtype: account?.subtype ?? '',
      parent_id: account?.parent_id ? String(account.parent_id) : '',
      description: account?.description ?? '',
      is_bank: account?.is_bank ?? false,
      is_cash: account?.is_cash ?? false,
      is_active: account?.is_active ?? true,
    });
  }, [open, account, reset]);

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body = { ...v, subtype: v.subtype || null, parent_id: v.parent_id ? Number(v.parent_id) : null, description: v.description || null } as unknown as Partial<Account>;
    try {
      if (account) await update.mutateAsync({ id: account.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  const hasLines = (account?.lines_count ?? 0) > 0;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={account ? `${account.code} · ${account.name}` : 'New account'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="account-form" loading={formState.isSubmitting}>{account ? 'Save account' : 'Create account'}</Button>
        </>
      }
    >
      <form id="account-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        {account?.is_system && <Alert variant="info" style={{ marginBottom: 16 }}>System account used by automatic postings. It cannot be deleted.</Alert>}
        <FormGrid columns={2}>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} /></FormField>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} /></FormField>
          <FormField label="Type" required error={e.type?.message} hint={hasLines ? 'Locked: account has postings' : undefined}>
            <Select {...register('type')} disabled={hasLines} options={TYPES} />
          </FormField>
          <FormField label="Subtype" hint="Drives report grouping and cash-flow classification" error={e.subtype?.message}>
            <Select {...register('subtype')} placeholder="—" options={(SUBTYPES[type ?? 'expense'] ?? []).map((s) => ({ value: s, label: humanize(s) }))} />
          </FormField>
          <FormField label="Parent account" span="full" error={e.parent_id?.message}>
            <Select
              {...register('parent_id')}
              placeholder="None"
              options={(parents.data?.data ?? []).filter((a) => a.id !== account?.id && a.type === type).map((a) => ({ value: String(a.id), label: `${a.code} · ${a.name}` }))}
            />
          </FormField>
          <FormField label="Description" span="full"><Textarea rows={2} {...register('description')} /></FormField>
        </FormGrid>
        {type === 'asset' && (
          <div style={{ display: 'flex', gap: 24, marginTop: 16, flexWrap: 'wrap' }}>
            <Controller control={control} name="is_bank" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Bank account" />} />
            <Controller control={control} name="is_cash" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Cash account" />} />
          </div>
        )}
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive accounts cannot receive new postings." />} />
        </div>
      </form>
    </Drawer>
  );
}

function AccountsInner() {
  const can = useCan();
  const list = useListState({ sort: 'code', perPage: 50 });
  const query = accounts.useList(list.apiParams);
  const del = accounts.useDelete();
  const [editing, setEditing] = useState<Account | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Account | null>(null);
  const canManage = can('accounts.manage');

  const columns: DataTableColumn<Account>[] = [
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, meta: { width: 110 }, cell: ({ row }) => <span className={styles.mono}>{row.original.code}</span> },
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Account',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>
            {row.original.is_system && <Lock size={12} style={{ display: 'inline', marginRight: 4, verticalAlign: '-1px' }} aria-label="System account" />}
            {row.original.name}
          </strong>
          <span>{row.original.parent ? `${row.original.parent.code} · ${row.original.parent.name}` : row.original.description ?? ''}</span>
        </div>
      ),
    },
    { id: 'type', accessorKey: 'type', header: 'Type', enableSorting: true, cell: ({ row }) => <Badge size="sm" variant={TYPE_VARIANT[row.original.type]}>{humanize(row.original.type)}</Badge> },
    { id: 'subtype', header: 'Subtype', cell: ({ row }) => (row.original.subtype ? humanize(row.original.subtype) : <span className={styles.muted}>—</span>) },
    { id: 'flags', header: 'Flags', cell: ({ row }) => <>{row.original.is_bank && <Badge size="sm">Bank</Badge>} {row.original.is_cash && <Badge size="sm">Cash</Badge>}</> },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Account>
        title="Chart of accounts"
        description="The ledger accounts every transaction posts to."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New account</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search code or name"
        filters={
          <>
            <Select aria-label="Type" placeholder="All types" value={list.state.filters.type ?? ''} onChange={(ev) => list.update({ filters: { type: ev.target.value || undefined } })} options={TYPES} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(ev) => list.update({ filters: { is_active: ev.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
          </>
        }
        emptyTitle="No accounts"
        emptyDescription="New companies receive a standard chart of accounts automatically."
        onRowClick={(a) => setEditing(a)}
        rowActions={(a) => (
          <>
            <MenuItem icon={<BookOpen size={16} />} href={`/finance/accounts/${a.id}`}>View ledger</MenuItem>
            {canManage && <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(a)}>Edit</MenuItem>}
            {canManage && !a.is_system && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(a)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />
      {canManage && <AccountForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} account={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete account ${deleting?.code}?`}
        description="Accounts with postings cannot be deleted — deactivate them instead."
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

export default function AccountsPage() {
  return (
    <Suspense>
      <AccountsInner />
    </Suspense>
  );
}
