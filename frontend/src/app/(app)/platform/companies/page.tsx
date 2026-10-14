'use client';

import { Suspense, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Building2, Plus, Power, PowerOff, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, ConfirmDialog, Drawer, FormField, FormGrid, FormSection, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch,
  type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { formatDate } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { platformCompanies, type PlatformCompany } from '@/features/platform/api';
import styles from '@/components/crud/crud.module.scss';

const CURRENCIES = ['USD', 'EUR', 'GBP', 'PKR', 'AED', 'SAR', 'INR', 'CAD', 'AUD'];
const TIMEZONES = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : ['UTC'];

const schema = z.object({
  name: z.string().trim().min(1, 'Company name is required').max(150),
  legal_name: z.string().max(200).optional(),
  email: z.email('Enter a valid email').optional().or(z.literal('')),
  country: z.string().trim().length(2, '2-letter code').optional().or(z.literal('')),
  currency: z.string().length(3),
  timezone: z.string().min(1),
  is_demo: z.boolean(),
  owner_name: z.string().trim().min(1, 'Owner name is required').max(150),
  owner_email: z.email('Enter the owner’s email'),
});
type Values = z.infer<typeof schema>;

function CompanyCreate({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [error, setError] = useState<string | null>(null);
  const { register, handleSubmit, setError: setFieldError, watch, setValue, formState } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { currency: 'USD', timezone: 'UTC', is_demo: false },
  });
  const create = platformCompanies.useCreate({ onSuccess: onClose });
  const onSubmit = async (v: Values) => {
    setError(null);
    try {
      await create.mutateAsync({
        name: v.name,
        legal_name: v.legal_name || null,
        email: v.email || null,
        country: v.country ? v.country.toUpperCase() : null,
        currency: v.currency,
        timezone: v.timezone,
        is_demo: v.is_demo,
        owner: { name: v.owner_name, email: v.owner_email },
      });
    } catch (e) {
      const err = e as ApiError;
      if (err.errors?.['owner.email']) setFieldError('owner_email', { message: err.errors['owner.email'][0] });
      if (err.errors?.['owner.name']) setFieldError('owner_name', { message: err.errors['owner.name'][0] });
      setError(applyServerErrors(err, setFieldError as never, ['name', 'legal_name', 'email', 'country', 'currency', 'timezone']));
    }
  };
  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Provision a company"
      description="Creates the company, a head office, the standard roles, chart of accounts and defaults, and invites the owner."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="company-form" loading={formState.isSubmitting}>Create company</Button>
        </>
      }
    >
      <form id="company-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormSection title="Company">
          <FormGrid columns={2}>
            <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
            <FormField label="Legal name" error={e.legal_name?.message}><Input {...register('legal_name')} /></FormField>
            <FormField label="Email" error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
            <FormField label="Country" hint="ISO code" error={e.country?.message}><Input maxLength={2} {...register('country')} /></FormField>
            <FormField label="Base currency" required><Select {...register('currency')} options={CURRENCIES.map((c) => ({ value: c, label: c }))} /></FormField>
            <FormField label="Time zone" required><Select {...register('timezone')} options={TIMEZONES.map((t) => ({ value: t, label: t }))} /></FormField>
          </FormGrid>
          <Switch checked={watch('is_demo')} onCheckedChange={(v) => setValue('is_demo', v)} label="Demo company" description="Clearly labelled as demo data throughout the app." />
        </FormSection>
        <FormSection title="Owner" description="Existing users are added as owner; new users receive an email to set their password.">
          <FormGrid columns={2}>
            <FormField label="Owner name" required error={e.owner_name?.message}><Input {...register('owner_name')} /></FormField>
            <FormField label="Owner email" required error={e.owner_email?.message}><Input type="email" {...register('owner_email')} /></FormField>
          </FormGrid>
        </FormSection>
      </form>
    </Drawer>
  );
}

function CompaniesInner() {
  const list = useListState({ sort: 'name' });
  const query = platformCompanies.useList(list.apiParams);
  const activate = platformCompanies.useAction('activate', { success: 'Company activated' });
  const deactivate = platformCompanies.useAction('deactivate', { success: 'Company deactivated' });
  const del = platformCompanies.useDelete();
  const [creating, setCreating] = useState(false);
  const [confirm, setConfirm] = useState<{ c: PlatformCompany; action: 'deactivate' | 'delete' } | null>(null);

  const columns: DataTableColumn<PlatformCompany>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Company',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.name} {row.original.is_demo && <Badge size="sm" variant="warning">Demo</Badge>}</strong>
          <span>{row.original.code} · {row.original.email ?? '—'}</span>
        </div>
      ),
    },
    { id: 'country', header: 'Country', cell: ({ row }) => row.original.country ?? '—' },
    { id: 'currency', header: 'Currency', cell: ({ row }) => row.original.currency },
    { id: 'users', header: 'Users', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.users_count ?? 0 },
    { id: 'branches', header: 'Branches', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.branches_count ?? 0 },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<PlatformCompany>
        title="Companies"
        description="All tenants on this platform. Platform staff cannot see company data unless they are a member of that company."
        actions={<Button leftIcon={<Plus size={16} />} onClick={() => setCreating(true)}>New company</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search companies"
        filters={
          <>
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(ev) => list.update({ filters: { is_active: ev.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
            <Select aria-label="Demo" placeholder="Demo & real" value={list.state.filters.is_demo ?? ''} onChange={(ev) => list.update({ filters: { is_demo: ev.target.value || undefined } })} options={[{ value: 'false', label: 'Real only' }, { value: 'true', label: 'Demo only' }]} />
          </>
        }
        emptyTitle="No companies yet"
        emptyAction={<Button leftIcon={<Building2 size={16} />} onClick={() => setCreating(true)}>Provision the first company</Button>}
        rowActions={(c) => (
          <>
            {c.is_active ? (
              <MenuItem icon={<PowerOff size={16} />} onSelect={() => setConfirm({ c, action: 'deactivate' })}>Deactivate</MenuItem>
            ) : (
              <MenuItem icon={<Power size={16} />} onSelect={() => activate.mutate({ id: c.id })}>Activate</MenuItem>
            )}
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive disabled={c.is_active} onSelect={() => setConfirm({ c, action: 'delete' })}>Delete</MenuItem>
          </>
        )}
      />
      <CompanyCreate key={creating ? 'open' : 'closed'} open={creating} onClose={() => setCreating(false)} />
      <ConfirmDialog
        open={confirm !== null}
        onClose={() => setConfirm(null)}
        title={confirm?.action === 'delete' ? `Delete ${confirm?.c.name}?` : `Deactivate ${confirm?.c.name}?`}
        description={confirm?.action === 'delete' ? 'The company is archived (soft-deleted). Its records are retained.' : 'Members will no longer be able to access this company until it is reactivated.'}
        destructive
        typeToConfirm={confirm?.action === 'delete' ? confirm.c.code : undefined}
        confirmLabel={confirm?.action === 'delete' ? 'Delete company' : 'Deactivate'}
        onConfirm={async () => {
          if (!confirm) return;
          if (confirm.action === 'delete') await del.mutateAsync(confirm.c.id);
          else await deactivate.mutateAsync({ id: confirm.c.id });
          setConfirm(null);
        }}
      />
    </>
  );
}

export default function PlatformCompaniesPage() {
  return (
    <Suspense>
      <CompaniesInner />
    </Suspense>
  );
}
