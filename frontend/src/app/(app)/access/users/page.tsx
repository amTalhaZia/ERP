'use client';

import { Suspense, useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation } from '@tanstack/react-query';
import { Mail, Pencil, Plus, UserMinus, UserPlus } from 'lucide-react';
import {
  Alert, Avatar, Badge, Button, Checkbox, ConfirmDialog, Drawer, FormField, FormGrid, Input, MenuItem, MenuSeparator, Select, StatusBadge, Switch, useToast,
  type DataTableColumn,
} from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { applyServerErrors } from '@/lib/crud/resource';
import { useCan } from '@/lib/auth/useCan';
import { formatRelative } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectBranches, selectUser } from '@/store/sessionSlice';
import { accessApi, companyUsers, roles, type CompanyUser } from '@/features/access/api';
import styles from '@/components/crud/crud.module.scss';

const schema = z.object({
  name: z.string().trim().min(1, 'Name is required').max(150),
  email: z.email('Enter a valid email'),
  role_ids: z.array(z.number()).min(1, 'Choose at least one role'),
  all_branches: z.boolean(),
  branch_ids: z.array(z.number()),
  is_active: z.boolean(),
});
type Values = z.infer<typeof schema>;

function UserForm({ open, user, onClose }: { open: boolean; user: CompanyUser | null; onClose: () => void }) {
  const branchesList = useAppSelector(selectBranches);
  const roleList = roles.useList({ per_page: 100, sort: 'name' }, { enabled: open });
  const create = companyUsers.useCreate({ onSuccess: onClose });
  const update = companyUsers.useUpdate({ onSuccess: onClose });
  const [formError, setFormError] = useState<string | null>(null);
  const { register, handleSubmit, reset, control, watch, setError, formState } = useForm<Values>({ resolver: zodResolver(schema) });

  useEffect(() => {
    if (!open) return;
    reset({
      name: user?.name ?? '',
      email: user?.email ?? '',
      role_ids: user?.roles?.map((r) => r.id) ?? [],
      all_branches: !user?.branch_ids,
      branch_ids: user?.branch_ids ?? [],
      is_active: user?.is_active ?? true,
    });
  }, [open, user, reset]);

  const allBranches = watch('all_branches');

  const onSubmit = async (v: Values) => {
    setFormError(null);
    const branch_ids = v.all_branches ? null : v.branch_ids;
    try {
      if (user) await update.mutateAsync({ id: user.id, body: { role_ids: v.role_ids, branch_ids, is_active: v.is_active } });
      else await create.mutateAsync({ name: v.name, email: v.email, role_ids: v.role_ids, branch_ids });
    } catch (e) {
      setFormError(applyServerErrors(e as ApiError, setError as never, ['name', 'email', 'role_ids', 'branch_ids', 'is_active']));
    }
  };

  const e = formState.errors;
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={user ? `Access for ${user.name}` : 'Invite a user'}
      description={user ? user.email : 'They will receive an email to set their own password.'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button type="submit" form="user-form" loading={formState.isSubmitting}>{user ? 'Save access' : 'Send invitation'}</Button>
        </>
      }
    >
      <form id="user-form" onSubmit={handleSubmit(onSubmit)} noValidate>
        {formError && <Alert variant="danger" style={{ marginBottom: 16 }}>{formError}</Alert>}
        {!user && (
          <FormGrid columns={2}>
            <FormField label="Full name" required error={e.name?.message}><Input {...register('name')} autoFocus /></FormField>
            <FormField label="Work email" required error={e.email?.message}><Input type="email" {...register('email')} /></FormField>
          </FormGrid>
        )}
        <FormField label="Roles" required error={e.role_ids?.message} hint="Permissions are the union of all assigned roles.">
          <Controller
            control={control}
            name="role_ids"
            render={({ field }) => (
              <div style={{ display: 'grid', gap: 8, gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))' }}>
                {(roleList.data?.data ?? []).map((r) => (
                  <Checkbox
                    key={r.id}
                    label={r.name}
                    description={r.description ?? undefined}
                    checked={field.value?.includes(r.id) ?? false}
                    onChange={(ev) => field.onChange(ev.target.checked ? [...(field.value ?? []), r.id] : (field.value ?? []).filter((x) => x !== r.id))}
                  />
                ))}
              </div>
            )}
          />
        </FormField>
        <div style={{ marginTop: 16 }}>
          <Controller control={control} name="all_branches" render={({ field }) => (
            <Switch checked={field.value} onCheckedChange={field.onChange} label="Access to all branches" description="Turn off to restrict this user to specific branches." />
          )} />
        </div>
        {!allBranches && (
          <FormField label="Allowed branches" error={e.branch_ids?.message}>
            <Controller
              control={control}
              name="branch_ids"
              render={({ field }) => (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                  {branchesList.map((b) => (
                    <Checkbox
                      key={b.id}
                      label={`${b.name} (${b.code})`}
                      checked={field.value?.includes(b.id) ?? false}
                      onChange={(ev) => field.onChange(ev.target.checked ? [...(field.value ?? []), b.id] : (field.value ?? []).filter((x) => x !== b.id))}
                    />
                  ))}
                </div>
              )}
            />
          </FormField>
        )}
        {user && (
          <div style={{ marginTop: 16 }}>
            <Controller control={control} name="is_active" render={({ field }) => (
              <Switch checked={field.value} onCheckedChange={field.onChange} label="Active in this company" description="Inactive members cannot sign in to this company." />
            )} />
          </div>
        )}
      </form>
    </Drawer>
  );
}

/** "All branches" when unrestricted; otherwise up to two names then "+N". */
function branchSummary(ids: number[] | null | undefined, names: Map<number, string>): string {
  if (!ids) return 'All branches';
  if (ids.length === 0) return 'No branches';
  const labels = ids.map((id) => names.get(id) ?? `Branch #${id}`);
  const shown = labels.slice(0, 2).join(', ');
  return labels.length > 2 ? `${shown} +${labels.length - 2}` : shown;
}

function UsersInner() {
  const can = useCan();
  const toast = useToast();
  const me = useAppSelector(selectUser);
  const allBranchList = useAppSelector(selectBranches);
  const list = useListState({ sort: 'name' });
  const query = companyUsers.useList(list.apiParams);
  const roleList = roles.useList({ per_page: 100, sort: 'name' });
  const remove = companyUsers.useDelete();
  const resend = useMutation({ mutationFn: accessApi.resendInvite, onSuccess: () => toast.success('Invitation re-sent') });
  const [editing, setEditing] = useState<CompanyUser | 'new' | null>(null);
  const [removing, setRemoving] = useState<CompanyUser | null>(null);
  const canManage = can('users.manage');
  const branchNames = new Map(allBranchList.map((b) => [b.id, b.name]));

  const columns: DataTableColumn<CompanyUser>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'User',
      enableSorting: true,
      enableHiding: false,
      cell: ({ row }) => (
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, minWidth: 0 }}>
          <Avatar name={row.original.name} src={row.original.avatar_url ?? undefined} size="sm" />
          <div className={styles.cellMain}>
            <strong>{row.original.name}{row.original.id === me?.id && <Badge size="sm" variant="neutral" style={{ marginLeft: 6 }}>You</Badge>}</strong>
            <span>{row.original.email}</span>
          </div>
        </div>
      ),
    },
    { id: 'roles', header: 'Roles', cell: ({ row }) => <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>{row.original.roles?.map((r) => <Badge key={r.id} variant="primary" size="sm">{r.name}</Badge>)}</div> },
    { id: 'branches', header: 'Branches', cell: ({ row }) => branchSummary(row.original.branch_ids, branchNames) },
    { id: 'last_login_at', accessorKey: 'last_login_at', header: 'Last sign-in', enableSorting: true, cell: ({ row }) => (row.original.last_login_at ? formatRelative(row.original.last_login_at) : <span className={styles.muted}>{row.original.email_verified_at ? 'Never' : 'Invite pending'}</span>) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active && row.original.user_is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<CompanyUser>
        title="Users"
        description="People with access to this company and their roles."
        actions={canManage && <Button leftIcon={<UserPlus size={16} />} onClick={() => setEditing('new')}>Invite user</Button>}
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name or email"
        filters={
          <>
            <Select aria-label="Role" placeholder="Any role" value={list.state.filters.role_id ?? ''} onChange={(ev) => list.update({ filters: { role_id: ev.target.value || undefined } })} options={(roleList.data?.data ?? []).map((r) => ({ value: String(r.id), label: r.name }))} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(ev) => list.update({ filters: { is_active: ev.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
          </>
        }
        emptyTitle="No users yet"
        emptyAction={canManage ? <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>Invite the first user</Button> : undefined}
        onRowClick={canManage ? (u) => setEditing(u) : undefined}
        rowActions={
          canManage
            ? (u) => (
                <>
                  <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(u)}>Edit access</MenuItem>
                  {!u.last_login_at && <MenuItem icon={<Mail size={16} />} onSelect={() => resend.mutate(u.id)}>Resend invitation</MenuItem>}
                  <MenuSeparator />
                  <MenuItem icon={<UserMinus size={16} />} destructive disabled={u.id === me?.id} onSelect={() => setRemoving(u)}>Remove from company</MenuItem>
                </>
              )
            : undefined
        }
      />
      <UserForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} user={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={removing !== null}
        onClose={() => setRemoving(null)}
        title={`Remove ${removing?.name}?`}
        description="They lose access to this company immediately. Their account and history in other companies are not affected."
        destructive
        confirmLabel="Remove"
        onConfirm={async () => {
          if (removing) await remove.mutateAsync(removing.id);
          setRemoving(null);
        }}
      />
    </>
  );
}

export default function UsersPage() {
  return (
    <Suspense>
      <UsersInner />
    </Suspense>
  );
}
