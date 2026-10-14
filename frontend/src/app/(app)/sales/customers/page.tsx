'use client';

import { Suspense, useMemo, useState } from 'react';
import dynamic from 'next/dynamic';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { List, Map as MapIcon, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, ConfirmDialog, EmptyState, ErrorState, MenuItem, MenuSeparator, PageHeader, SegmentedControl, Select, Skeleton, StatusBadge,
  type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import type { MapPoint } from '@/components/maps/LocationsMap';
import { customers, salesApi, CUSTOMER_TYPES, type Customer } from '@/features/sales/api';
import { CustomerForm } from '@/features/sales/CustomerForm';
import { enumOptions, useUserOptions } from '@/features/sales/shared';
import styles from '@/components/crud/crud.module.scss';

const LocationsMap = dynamic(() => import('@/components/maps/LocationsMap'), {
  ssr: false,
  loading: () => <Skeleton style={{ height: 520 }} />,
});

function CustomersMap() {
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const q = useQuery({ queryKey: [companyId, 'customers', 'map'], queryFn: salesApi.customerMap });
  const points: MapPoint[] = useMemo(
    () =>
      (q.data ?? []).map((c) => ({
        id: `customer-${c.id}`,
        lat: Number(c.latitude),
        lng: Number(c.longitude),
        title: c.name,
        subtitle: [c.code, c.city, c.country].filter(Boolean).join(' · '),
        kind: 'customer' as const,
        href: `/sales/customers/${c.id}`,
      })),
    [q.data],
  );

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  return (
    <>
      <Alert variant="info">
        Showing active customers with saved coordinates{q.data ? ` (${q.data.length})` : ''}. Pins mark the saved address, not live positions. Add latitude/longitude on a customer to place it here.
      </Alert>
      <Card>
        <CardBody padding="sm">
          {q.isLoading ? (
            <Skeleton style={{ height: 520 }} />
          ) : points.length === 0 ? (
            <EmptyState icon={<MapIcon size={22} />} title="No customers with coordinates" description="Edit a customer and add latitude and longitude to see it on the map." />
          ) : (
            <LocationsMap points={points} />
          )}
        </CardBody>
      </Card>
    </>
  );
}

function CustomersInner() {
  const can = useCan();
  const router = useRouter();
  const list = useListState({ sort: 'name' });
  const [view, setView] = useState<'list' | 'map'>('list');
  const query = customers.useList(list.apiParams, { enabled: view === 'list' });
  const del = customers.useDelete();
  const reps = useUserOptions();
  const [editing, setEditing] = useState<Customer | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Customer | null>(null);
  const canManage = can('customers.manage');

  const columns: DataTableColumn<Customer>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Customer',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong><Link href={`/sales/customers/${row.original.id}`} onClick={(e) => e.stopPropagation()}>{row.original.name}</Link></strong>
          <span>{row.original.email ?? row.original.phone ?? '—'}</span>
        </div>
      ),
    },
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.code}</span> },
    { id: 'type', header: 'Type', cell: ({ row }) => humanize(row.original.type) },
    {
      id: 'location',
      header: 'Location',
      cell: ({ row }) => [row.original.billing_address.city, row.original.billing_address.country].filter(Boolean).join(', ') || <span className={styles.muted}>—</span>,
    },
    { id: 'rep', header: 'Sales rep', cell: ({ row }) => row.original.sales_rep?.name ?? <span className={styles.muted}>Unassigned</span> },
    { id: 'payment_terms_days', accessorKey: 'payment_terms_days', header: 'Terms', enableSorting: true, meta: { numeric: true }, cell: ({ row }) => `${row.original.payment_terms_days} d` },
    {
      id: 'credit_limit',
      accessorKey: 'credit_limit',
      header: 'Credit limit',
      enableSorting: true,
      meta: { numeric: true },
      cell: ({ row }) => (row.original.credit_limit === null ? <span className={styles.muted}>No limit</span> : formatMoney(row.original.credit_limit, row.original.currency)),
    },
    { id: 'contacts', header: 'Contacts', meta: { numeric: true }, cell: ({ row }) => row.original.contacts_count ?? 0 },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  const toggle = (
    <SegmentedControl
      aria-label="View"
      value={view}
      onChange={setView}
      size="sm"
      options={[
        { value: 'list', label: 'List', icon: <List size={14} /> },
        { value: 'map', label: 'Map', icon: <MapIcon size={14} /> },
      ]}
    />
  );
  const newBtn = canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New customer</Button>;

  return (
    <>
      {view === 'map' ? (
        <div className={styles.page}>
          <PageHeader title="Customers" description="Customer locations on the map." actions={<>{toggle}{newBtn}</>} />
          <CustomersMap />
        </div>
      ) : (
        <ListPage<Customer>
          title="Customers"
          description="Accounts you sell to, with contacts, terms and history."
          actions={<>{toggle}{newBtn}</>}
          query={query}
          columns={columns}
          list={list}
          searchPlaceholder="Search name, code or email"
          filters={
            <>
              <Select
                aria-label="Status"
                placeholder="Any status"
                value={list.state.filters.is_active ?? ''}
                onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })}
                options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]}
              />
              <Select
                aria-label="Type"
                placeholder="Any type"
                value={list.state.filters.type ?? ''}
                onChange={(e) => list.update({ filters: { type: e.target.value || undefined } })}
                options={enumOptions(CUSTOMER_TYPES)}
              />
              <Select
                aria-label="Sales rep"
                placeholder="Any sales rep"
                value={list.state.filters.sales_rep_id ?? ''}
                onChange={(e) => list.update({ filters: { sales_rep_id: e.target.value || undefined } })}
                options={reps.options}
              />
            </>
          }
          emptyTitle="No customers yet"
          emptyDescription="Add the accounts you sell to, or convert a qualified lead."
          emptyAction={canManage ? <Button leftIcon={<UserPlus size={16} />} onClick={() => setEditing('new')}>Add a customer</Button> : undefined}
          onRowClick={(c) => router.push(`/sales/customers/${c.id}`)}
          rowActions={
            canManage
              ? (c) => (
                  <>
                    <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(c)}>Edit</MenuItem>
                    <MenuSeparator />
                    <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(c)}>Delete</MenuItem>
                  </>
                )
              : undefined
          }
        />
      )}

      <CustomerForm
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id}
        open={editing !== null}
        customer={editing === 'new' ? null : editing}
        onClose={() => setEditing(null)}
        onSaved={(c) => {
          if (editing === 'new') router.push(`/sales/customers/${c.id}`);
        }}
      />

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="The customer will be archived. Quotations and orders already issued keep their history."
        destructive
        confirmLabel="Delete customer"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function CustomersPage() {
  return (
    <Suspense>
      <CustomersInner />
    </Suspense>
  );
}
