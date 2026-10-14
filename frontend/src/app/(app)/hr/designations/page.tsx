'use client';

import { Suspense, useState } from 'react';
import Link from 'next/link';
import { BadgeCheck, Pencil, Plus, Trash2 } from 'lucide-react';
import { Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { designations, type Designation } from '@/features/hr/api';
import { DesignationForm } from '@/features/hr/SimpleForms';
import crud from '@/components/crud/crud.module.scss';

function DesignationsInner() {
  const can = useCan();
  const list = useListState({ sort: 'level' });
  const query = designations.useList(list.apiParams);
  const del = designations.useDelete();
  const [editing, setEditing] = useState<Designation | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Designation | null>(null);
  const canManage = can('employees.update');

  const columns: DataTableColumn<Designation>[] = [
    { id: 'title', accessorKey: 'title', header: 'Title', enableSorting: true, enableHiding: false, cell: ({ row }) => <strong>{row.original.title}</strong> },
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.code}</span> },
    { id: 'level', accessorKey: 'level', header: 'Level', enableSorting: true, meta: { numeric: true, align: 'right' } },
    { id: 'employees', header: 'Employees', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.employees_count ?? 0 },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Designation>
        title="Designations"
        description={<>Job titles used on employee profiles. <Link href="/hr/employees">Back to employees</Link></>}
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New designation</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search title or code"
        filters={
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''}
            onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })}
            options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
        }
        emptyTitle="No designations yet"
        emptyDescription="Create job titles such as Engineer, Team Lead or HR Manager."
        emptyAction={canManage ? <Button leftIcon={<BadgeCheck size={16} />} onClick={() => setEditing('new')}>Add a designation</Button> : undefined}
        onRowClick={canManage ? (row) => setEditing(row) : undefined}
        rowActions={canManage ? (row) => (
          <>
            <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(row)}>Edit</MenuItem>
            <MenuSeparator />
            <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(row)}>Delete</MenuItem>
          </>
        ) : undefined}
      />
      <DesignationForm
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id}
        open={editing !== null}
        row={editing === 'new' ? null : editing}
        onClose={() => setEditing(null)}
      />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.title ?? 'designation'}?`}
        description="Designations still held by employees cannot be deleted — reassign them or deactivate the designation instead."
        destructive
        confirmLabel="Delete"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function DesignationsPage() {
  return (
    <Suspense>
      <DesignationsInner />
    </Suspense>
  );
}
