'use client';

import { Suspense, useState } from 'react';
import { Building2, MapPin, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge, Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { branches, type Branch } from '@/features/company/api';
import { BranchForm } from '@/features/company/BranchForm';
import styles from '@/components/crud/crud.module.scss';

function BranchesInner() {
  const can = useCan();
  const list = useListState({ sort: 'name' });
  const query = branches.useList(list.apiParams);
  const del = branches.useDelete();
  const [editing, setEditing] = useState<Branch | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Branch | null>(null);
  const canManage = can('branches.manage');

  const columns: DataTableColumn<Branch>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Branch',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>
            {row.original.name} {row.original.is_head_office && <Badge variant="primary" size="sm">Head office</Badge>}
          </strong>
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
      meta: { sortKey: 'city' },
      cell: ({ row }) => [row.original.address.city, row.original.address.country].filter(Boolean).join(', ') || <span className={styles.muted}>—</span>,
    },
    { id: 'departments', header: 'Departments', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.departments_count ?? 0 },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Branch>
        title="Branches"
        description="Offices, sites and regional locations of this company."
        actions={
          <>
            <Button variant="secondary" href="/company/map" leftIcon={<MapPin size={16} />}>Map</Button>
            {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New branch</Button>}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, code or city"
        filters={
          <Select
            aria-label="Status"
            placeholder="Any status"
            value={list.state.filters.is_active ?? ''}
            onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })}
            options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]}
          />
        }
        emptyTitle="No branches yet"
        emptyDescription="Add the locations your company operates from."
        emptyAction={canManage ? <Button leftIcon={<Building2 size={16} />} onClick={() => setEditing('new')}>Add a branch</Button> : undefined}
        onRowClick={canManage ? (b) => setEditing(b) : undefined}
        rowActions={
          canManage
            ? (b) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(b)}>Edit</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive disabled={b.is_head_office} onSelect={() => setDeleting(b)}>
                    Delete
                  </MenuItem>
                </>
              )
            : undefined
        }
      />

      <BranchForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} branch={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="The branch will be archived. Records already linked to it keep their history."
        destructive
        confirmLabel="Delete branch"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function BranchesPage() {
  return (
    <Suspense>
      <BranchesInner />
    </Suspense>
  );
}
