'use client';

import { Suspense, useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useQuery } from '@tanstack/react-query';
import { Network, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch, Textarea,
  type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { branches, companyApi, departments, type Department, type DepartmentNode } from '@/features/company/api';
import styles from '@/components/crud/crud.module.scss';

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  code: z.string().trim().min(1, 'Code is required').max(20).regex(/^[A-Za-z0-9_-]+$/, 'Letters, numbers, - and _ only'),
  description: z.string().max(500).optional(),
  branch_id: z.string().optional(),
  parent_id: z.string().optional(),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

function flatten(nodes: DepartmentNode[], depth = 0): { id: number; label: string }[] {
  return nodes.flatMap((n) => [{ id: n.id, label: `${'— '.repeat(depth)}${n.name}` }, ...flatten(n.children, depth + 1)]);
}

function DepartmentForm({ open, department, onClose }: { open: boolean; department: Department | null; onClose: () => void }) {
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, reset, setError, control, formState } = useForm<Values>({ resolver: zodResolver(schema) });
  const create = departments.useCreate({ onSuccess: onClose });
  const update = departments.useUpdate({ onSuccess: onClose });
  const branchList = branches.useAll('all', {}, { enabled: open });
  const tree = useQuery({ queryKey: [companyId, 'departments', 'tree'], queryFn: companyApi.departmentTree, enabled: open });

  useEffect(() => {
    if (!open) return;
    reset({
      name: department?.name ?? '',
      code: department?.code ?? '',
      description: department?.description ?? '',
      branch_id: department?.branch_id ? String(department.branch_id) : '',
      parent_id: department?.parent_id ? String(department.parent_id) : '',
      is_active: department?.is_active ?? true,
    });
  }, [open, department, reset]);

  const onSubmit = async (v: Values) => {
    const body = {
      ...v,
      description: v.description || null,
      branch_id: v.branch_id ? Number(v.branch_id) : null,
      parent_id: v.parent_id ? Number(v.parent_id) : null,
    };
    try {
      if (department) await update.mutateAsync({ id: department.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, Object.keys(schema.shape)));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={department ? `Edit ${department.name}` : 'New department'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="dept-form" loading={formState.isSubmitting}>{department ? 'Save changes' : 'Create department'}</Button>
        </>
      }
    >
      <form id="dept-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
          <FormField label="Code" required error={e.code?.message}><Input {...register('code')} /></FormField>
          <FormField label="Parent department" error={e.parent_id?.message}>
            <Select
              {...register('parent_id')}
              placeholder="None (top level)"
              options={flatten(tree.data ?? []).filter((o) => o.id !== department?.id).map((o) => ({ value: String(o.id), label: o.label }))}
            />
          </FormField>
          <FormField label="Branch" error={e.branch_id?.message}>
            <Select {...register('branch_id')} placeholder="All branches" options={(branchList.data ?? []).map((b) => ({ value: String(b.id), label: b.name }))} />
          </FormField>
          <FormField label="Description" span="full" error={e.description?.message}><Textarea rows={3} {...register('description')} /></FormField>
          <Controller control={control} name="is_active" render={({ field }) => <Switch checked={field.value} onCheckedChange={field.onChange} label="Active" />} />
        </FormGrid>
      </form>
    </Drawer>
  );
}

function DepartmentsInner() {
  const can = useCan();
  const list = useListState({ sort: 'name' });
  const query = departments.useList(list.apiParams);
  const del = departments.useDelete();
  const [editing, setEditing] = useState<Department | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Department | null>(null);
  const canManage = can('departments.manage');

  const columns: DataTableColumn<Department>[] = [
    { id: 'name', accessorKey: 'name', header: 'Department', enableSorting: true, cell: ({ row }) => <div className={styles.cellMain}><strong>{row.original.name}</strong><span>{row.original.description ?? ''}</span></div> },
    { id: 'code', accessorKey: 'code', header: 'Code', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.code}</span> },
    { id: 'parent', header: 'Parent', cell: ({ row }) => row.original.parent?.name ?? <span className={styles.muted}>—</span> },
    { id: 'branch', header: 'Branch', cell: ({ row }) => row.original.branch?.name ?? <span className={styles.muted}>All</span> },
    { id: 'children', header: 'Sub-depts', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.children_count ?? 0 },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Department>
        title="Departments"
        description="Organisational units used by HR, approvals and reporting."
        actions={
          <>
            <Button variant="secondary" href="/hr/org-chart" leftIcon={<Network size={16} />}>Org chart</Button>
            {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New department</Button>}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name or code"
        emptyTitle="No departments yet"
        emptyAction={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Add a department</Button> : undefined}
        onRowClick={canManage ? (d) => setEditing(d) : undefined}
        rowActions={
          canManage
            ? (d) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(d)}>Edit</MenuItem>
                  <MenuSeparator />
                  <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(d)}>Delete</MenuItem>
                </>
              )
            : undefined
        }
      />
      <DepartmentForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} department={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name}?`}
        description="Departments with sub-departments must be emptied first."
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

export default function DepartmentsPage() {
  return (
    <Suspense>
      <DepartmentsInner />
    </Suspense>
  );
}
