'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Eye, MapPin, Pencil, Plus, Trash2, Warehouse as WarehouseIcon } from 'lucide-react';
import { Badge, Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { branches } from '@/features/company/api';
import { warehouses, type Warehouse } from '@/features/inventory/api';
import { WarehouseForm } from '@/features/inventory/WarehouseForm';
import styles from '@/components/crud/crud.module.scss';

function WarehousesInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: 'name' });
  const query = warehouses.useList(list.apiParams);
  const branchList = branches.useAll('all');
  const del = warehouses.useDelete();
  const [editing, setEditing] = useState<Warehouse | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Warehouse | null>(null);
  const canManage = can('warehouses.manage');

  const columns: DataTableColumn<Warehouse>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Warehouse',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>
            {row.original.name} {row.original.is_default && <Badge variant="primary" size="sm">Default</Badge>}
          </strong>
          <span>{row.original.branch?.name ?? 'Company-wide'}</span>
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
    { id: 'locations', header: 'Storage locations', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.locations_count ?? 0 },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Warehouse>
        title="Warehouses"
        description="Places where stock is held. Each can be split into zones, aisles, racks and bins."
        actions={
          <>
            <Button variant="secondary" href="/company/map" leftIcon={<MapPin size={16} />}>Map</Button>
            {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New warehouse</Button>}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, code or city"
        filters={
          <>
            <Select aria-label="Branch" placeholder="Any branch" value={list.state.filters.branch_id ?? ''} onChange={(e) => list.update({ filters: { branch_id: e.target.value || undefined } })} options={(branchList.data ?? []).map((b) => ({ value: String(b.id), label: b.name }))} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
          </>
        }
        emptyTitle="No warehouses yet"
        emptyDescription="Add the locations where you hold stock."
        emptyAction={canManage ? <Button leftIcon={<WarehouseIcon size={16} />} onClick={() => setEditing('new')}>Add a warehouse</Button> : undefined}
        onRowClick={(w) => router.push(`/inventory/warehouses/${w.id}`)}
        rowActions={(w) => (
          <>
            <MenuItem icon={<Eye size={16} />} href={`/inventory/warehouses/${w.id}`}>View</MenuItem>
            {canManage && <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(w)}>Edit</MenuItem>}
            {canManage && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(w)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />

      {canManage && (
        <WarehouseForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} warehouse={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Warehouses that still hold stock cannot be deleted — transfer the stock out first."
        destructive
        confirmLabel="Delete warehouse"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function WarehousesPage() {
  return (
    <Suspense>
      <WarehousesInner />
    </Suspense>
  );
}
