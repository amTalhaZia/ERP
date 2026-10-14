'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Eye, Pencil, Plus, ShieldCheck, Trash2, Truck } from 'lucide-react';
import { Badge, Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { suppliers, type Supplier } from '@/features/procurement/api';
import { SupplierForm } from '@/features/procurement/SupplierForm';
import { Stars } from '@/features/procurement/components';
import styles from '@/components/crud/crud.module.scss';

function SuppliersInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: 'name' });
  const query = suppliers.useList(list.apiParams);
  const del = suppliers.useDelete();
  const approve = suppliers.useAction('approve', { success: 'Supplier approved' });
  const [editing, setEditing] = useState<Supplier | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Supplier | null>(null);
  const canManage = can('suppliers.manage');

  const columns: DataTableColumn<Supplier>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Supplier',
      enableSorting: true,
      enableHiding: false,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.name}</strong>
          <span>{row.original.email ?? row.original.phone ?? '—'}</span>
        </div>
      ),
    },
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.code}</span> },
    {
      id: 'city',
      accessorKey: 'city',
      header: 'Location',
      enableSorting: true,
      cell: ({ row }) => [row.original.address.city, row.original.address.country].filter(Boolean).join(', ') || <span className={styles.muted}>—</span>,
    },
    { id: 'terms', header: 'Terms', cell: ({ row }) => `${row.original.payment_terms_days} days · ${row.original.currency}` },
    { id: 'rating', accessorKey: 'rating', header: 'Rating', enableSorting: true, cell: ({ row }) => <Stars rating={row.original.rating} /> },
    { id: 'orders', header: 'POs', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.purchase_orders_count ?? 0 },
    {
      id: 'status',
      header: 'Status',
      cell: ({ row }) => (
        <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
          <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} />
          {row.original.is_approved ? <Badge size="sm" variant="success">Approved</Badge> : <Badge size="sm" variant="warning">Unapproved</Badge>}
        </div>
      ),
    },
  ];

  return (
    <>
      <ListPage<Supplier>
        title="Suppliers"
        description="Vendors you buy from, their terms, contacts and approval status."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New supplier</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, code, email, city or tax no."
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
              aria-label="Approval"
              placeholder="Any approval"
              value={list.state.filters.is_approved ?? ''}
              onChange={(e) => list.update({ filters: { is_approved: e.target.value || undefined } })}
              options={[{ value: 'true', label: 'Approved' }, { value: 'false', label: 'Not approved' }]}
            />
          </>
        }
        emptyTitle="No suppliers yet"
        emptyDescription="Add the vendors you purchase goods and services from."
        emptyAction={canManage ? <Button leftIcon={<Truck size={16} />} onClick={() => setEditing('new')}>Add a supplier</Button> : undefined}
        onRowClick={(s) => router.push(`/procurement/suppliers/${s.id}`)}
        rowActions={(s) => (
          <>
            <MenuItem icon={<Eye size={16} />} onSelect={() => router.push(`/procurement/suppliers/${s.id}`)}>View</MenuItem>
            {canManage && <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(s)}>Edit</MenuItem>}
            {canManage && !s.is_approved && (
              <MenuItem icon={<ShieldCheck size={16} />} onSelect={() => approve.mutate({ id: s.id })}>Approve</MenuItem>
            )}
            {canManage && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(s)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />

      <SupplierForm
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id}
        open={editing !== null}
        supplier={editing === 'new' ? null : editing}
        onClose={() => setEditing(null)}
      />

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="The supplier will be archived. Existing purchase orders keep their history."
        destructive
        confirmLabel="Delete supplier"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function SuppliersPage() {
  return (
    <Suspense>
      <SuppliersInner />
    </Suspense>
  );
}
