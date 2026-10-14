'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { BadgeCheck, Eye, MoreHorizontal, Network, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import { Button, ConfirmDialog, IconButton, Menu, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { departments } from '@/features/company/api';
import { EMPLOYEE_STATUSES, EMPLOYMENT_TYPES, employees, typeLabel, useDesignationOptions, type Employee } from '@/features/hr/api';
import { EmployeeCell } from '@/features/hr/components';
import crud from '@/components/crud/crud.module.scss';

function EmployeesInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: 'last_name' });
  const query = employees.useList(list.apiParams);
  const branches = useAppSelector(selectBranches);
  const deps = departments.useList({ per_page: 100, sort: 'name' });
  const desig = useDesignationOptions();
  const del = employees.useDelete();
  const [deleting, setDeleting] = useState<Employee | null>(null);
  const f = list.state.filters;
  const setFilter = (k: string) => (e: { target: { value: string } }) => list.update({ filters: { [k]: e.target.value || undefined } });

  const columns: DataTableColumn<Employee>[] = [
    {
      id: 'last_name',
      accessorKey: 'last_name',
      header: 'Employee',
      enableSorting: true,
      enableHiding: false,
      cell: ({ row }) => <EmployeeCell name={row.original.name} photo={row.original.photo_url} sub={row.original.email} />,
    },
    { id: 'employee_code', accessorKey: 'employee_code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.employee_code}</span> },
    { id: 'designation', header: 'Designation', cell: ({ row }) => row.original.designation?.title ?? <span className={crud.muted}>—</span> },
    { id: 'department', header: 'Department', cell: ({ row }) => row.original.department?.name ?? <span className={crud.muted}>—</span> },
    { id: 'branch', header: 'Branch', cell: ({ row }) => row.original.branch?.name ?? <span className={crud.muted}>—</span> },
    { id: 'type', header: 'Type', cell: ({ row }) => typeLabel(row.original.employment_type) },
    { id: 'hired_at', accessorKey: 'hired_at', header: 'Hired', enableSorting: true, cell: ({ row }) => formatDate(row.original.hired_at) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <>
      <ListPage<Employee>
        title="Employees"
        description="Everyone on the books — profiles, reporting lines and employment details."
        actions={
          <>
            <Menu
              align="end"
              trigger={<IconButton icon={<MoreHorizontal size={18} />} aria-label="More employee actions" variant="secondary" />}
            >
              <MenuItem icon={<Network size={16} />} href="/hr/org-chart">Org chart</MenuItem>
              <MenuItem icon={<BadgeCheck size={16} />} href="/hr/designations">Manage designations</MenuItem>
            </Menu>
            {can('employees.create') && (
              <Button leftIcon={<Plus size={16} />} href="/hr/employees/new">New employee</Button>
            )}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name, code, email or phone"
        filters={
          <>
            <Select aria-label="Department" placeholder="Any department" value={f.department_id ?? ''} onChange={setFilter('department_id')}
              options={(deps.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name }))} />
            <Select aria-label="Branch" placeholder="Any branch" value={f.branch_id ?? ''} onChange={setFilter('branch_id')}
              options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
            <Select aria-label="Designation" placeholder="Any designation" value={f.designation_id ?? ''} onChange={setFilter('designation_id')} options={desig.options} />
            <Select aria-label="Status" placeholder="Any status" value={f.status ?? ''} onChange={setFilter('status')} options={EMPLOYEE_STATUSES} />
            <Select aria-label="Employment type" placeholder="Any type" value={f.employment_type ?? ''} onChange={setFilter('employment_type')} options={EMPLOYMENT_TYPES} />
          </>
        }
        emptyTitle="No employees yet"
        emptyDescription="Add your first employee to start tracking attendance, leave and performance."
        emptyAction={can('employees.create') ? <Button leftIcon={<UserPlus size={16} />} href="/hr/employees/new">Add an employee</Button> : undefined}
        onRowClick={(row) => router.push(`/hr/employees/${row.id}`)}
        rowActions={(row) => (
          <>
            <MenuItem icon={<Eye size={16} />} onSelect={() => router.push(`/hr/employees/${row.id}`)}>View profile</MenuItem>
            {can('employees.update') && <MenuItem icon={<Pencil size={16} />} onSelect={() => router.push(`/hr/employees/${row.id}/edit`)}>Edit</MenuItem>}
            {can('employees.delete') && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(row)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'employee'}?`}
        description="The record is archived. Employees with direct reports must have them reassigned first. To record a departure, terminate instead."
        destructive
        confirmLabel="Delete employee"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function EmployeesPage() {
  return (
    <Suspense>
      <EmployeesInner />
    </Suspense>
  );
}
