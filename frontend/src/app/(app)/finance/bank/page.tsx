'use client';

import { Suspense, useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, CurrencyInput, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch, type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { formatMoney } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { bankAccountsFull, useAccountOptions, useCurrency, type BankAccountFull, type BankAccountInput } from '@/features/finance/receivables/api';
import crud from '@/components/crud/crud.module.scss';

const schema = z.object({
  account_id: z.string().min(1, 'Choose the GL account'),
  name: z.string().trim().min(1, 'Name is required').max(150),
  bank_name: z.string().trim().max(150),
  account_number: z.string().trim().max(64),
  iban: z.string().trim().max(34).regex(/^[A-Za-z0-9]*$/, 'Letters and digits only, no spaces'),
  swift: z.string().trim().max(20).regex(/^[A-Za-z0-9]*$/, 'Letters and digits only'),
  currency: z.string().trim().regex(/^([A-Za-z]{3})?$/, 'Three-letter ISO code'),
  opening_balance: z.string().nullable(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

function BankAccountForm({ bank, onClose }: { bank: BankAccountFull | null; onClose: () => void }) {
  const companyCurrency = useCurrency();
  const [formError, setFormError] = useState<string | null>(null);
  const gl = useAccountOptions('asset', { filter: (a) => a.is_bank || a.is_cash });
  const create = bankAccountsFull.useCreate({ onSuccess: onClose });
  const update = bankAccountsFull.useUpdate({ onSuccess: onClose });
  const { register, handleSubmit, control, setError, watch, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      account_id: bank ? String(bank.account_id) : '',
      name: bank?.name ?? '',
      bank_name: bank?.bank_name ?? '',
      account_number: '',
      iban: '',
      swift: bank?.swift ?? '',
      currency: bank?.currency ?? companyCurrency,
      opening_balance: bank?.opening_balance ?? null,
      is_active: bank?.is_active ?? true,
    },
  });
  const currency = watch('currency') || companyCurrency;

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const body: BankAccountInput = {
      account_id: Number(v.account_id),
      name: v.name,
      bank_name: v.bank_name || null,
      swift: v.swift ? v.swift.toUpperCase() : null,
      currency: v.currency ? v.currency.toUpperCase() : null,
      opening_balance: v.opening_balance || '0',
      is_active: v.is_active,
    };
    // Full numbers are never returned by the API: only send them when (re)entered.
    if (!bank || v.account_number) body.account_number = v.account_number || null;
    if (!bank || v.iban) body.iban = v.iban ? v.iban.toUpperCase() : null;
    try {
      if (bank) await update.mutateAsync({ id: bank.id, body });
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
      title={bank ? bank.name : 'New bank account'}
      description="Cash and bank accounts that receipts and payments move money through."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="bank-form" loading={formState.isSubmitting}>{bank ? 'Save' : 'Create'}</Button>
        </>
      }
    >
      <form id="bank-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="GL account" required span="full" error={e.account_id?.message} hint={gl.query.isSuccess && gl.rows.length === 0 ? 'No asset accounts are flagged Bank or Cash — flag one in the chart of accounts.' : 'Asset accounts flagged Bank or Cash'}>
            <Select {...register('account_id')} placeholder="Select account" options={gl.options} />
          </FormField>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} placeholder="e.g. Operating account" /></FormField>
          <FormField label="Bank name" error={e.bank_name?.message}><Input {...register('bank_name')} /></FormField>
          <FormField label="Account number" error={e.account_number?.message} hint={bank?.account_number_masked ? `Current: ${bank.account_number_masked} — leave blank to keep` : undefined}>
            <Input {...register('account_number')} autoComplete="off" />
          </FormField>
          <FormField label="IBAN" error={e.iban?.message} hint={bank?.iban_masked ? `Current: ${bank.iban_masked} — leave blank to keep` : undefined}>
            <Input {...register('iban')} autoComplete="off" />
          </FormField>
          <FormField label="SWIFT / BIC" error={e.swift?.message}><Input {...register('swift')} /></FormField>
          <FormField label="Currency" error={e.currency?.message}><Input {...register('currency')} maxLength={3} style={{ textTransform: 'uppercase' }} /></FormField>
          <FormField label="Opening balance" error={e.opening_balance?.message}>
            <Controller control={control} name="opening_balance" render={({ field }) => <CurrencyInput currency={/^[A-Za-z]{3}$/.test(currency) ? currency.toUpperCase() : companyCurrency} allowNegative value={field.value} onValueChange={field.onChange} />} />
          </FormField>
        </FormGrid>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" description="Inactive accounts can't be used for new receipts or payments." />} />
        </div>
      </form>
    </Drawer>
  );
}

function BankInner() {
  const can = useCan();
  const list = useListState({ sort: 'name' });
  const query = bankAccountsFull.useList(list.apiParams);
  const del = bankAccountsFull.useDelete();
  const [editing, setEditing] = useState<BankAccountFull | 'new' | null>(null);
  const [deleting, setDeleting] = useState<BankAccountFull | null>(null);
  const canManage = can('bank.manage');

  const columns: DataTableColumn<BankAccountFull>[] = [
    { id: 'name', accessorKey: 'name', header: 'Account', enableSorting: true, cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.name}</strong><span>{row.original.bank_name ?? ''}</span></div> },
    { id: 'number', header: 'Number', cell: ({ row }) => <div className={crud.cellMain}><strong className={crud.mono}>{row.original.account_number_masked ?? '—'}</strong><span>{row.original.iban_masked ?? ''}</span></div> },
    { id: 'swift', header: 'SWIFT', cell: ({ row }) => row.original.swift ?? <span className={crud.muted}>—</span> },
    { id: 'gl', header: 'GL account', cell: ({ row }) => (row.original.account ? <><span className={crud.mono}>{row.original.account.code}</span> {row.original.account.name}</> : '—') },
    { id: 'currency', header: 'Currency', cell: ({ row }) => <Badge size="sm">{row.original.currency}</Badge> },
    { id: 'opening_balance', header: 'Opening balance', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.opening_balance, row.original.currency) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<BankAccountFull>
        title="Bank & cash accounts"
        description="Account numbers and IBANs are stored securely and only shown masked."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New bank account</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, bank or last 4 digits"
        filters={
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(ev) => list.update({ filters: { is_active: ev.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
        }
        emptyTitle="No bank accounts"
        emptyDescription="Add the bank and cash accounts used to receive and pay money."
        emptyAction={canManage ? <Button size="sm" leftIcon={<Landmark size={14} />} onClick={() => setEditing('new')}>Add bank account</Button> : undefined}
        onRowClick={canManage ? (b) => setEditing(b) : undefined}
        rowActions={
          canManage
            ? (b) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(b)}>Edit</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(b)}>Delete</MenuItem>
                </>
              )
            : undefined
        }
      />
      {canManage && editing !== null && <BankAccountForm key={editing === 'new' ? 'new' : editing.id} bank={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? ''}?`}
        description="Accounts used by payments should be deactivated instead."
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

export default function BankPage() {
  return (
    <Suspense>
      <BankInner />
    </Suspense>
  );
}
