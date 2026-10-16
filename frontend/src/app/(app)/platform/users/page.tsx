'use client';

import { Suspense, useState } from 'react';
import { ShieldCheck, ShieldOff, UserCheck, UserX } from 'lucide-react';
import { Avatar, Badge, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { formatRelative } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { platformCompanies, platformUsers, type PlatformUser } from '@/features/platform/api';
import styles from '@/components/crud/crud.module.scss';

function PlatformUsersInner() {
  const me = useAppSelector(selectUser);
  const isOwner = me?.platform_role === 'platform.owner';
  const list = useListState({ sort: 'name' });
  const query = platformUsers.useList(list.apiParams);
  const companies = platformCompanies.useList({ per_page: 100, sort: 'name' });
  const update = platformUsers.useUpdate();
  const [confirm, setConfirm] = useState<{ u: PlatformUser; body: { is_active?: boolean; platform_role?: string | null }; title: string; description: string } | null>(null);

  const columns: DataTableColumn<PlatformUser>[] = [
    {
      id: 'name',
      accessorKey: 'name',
      header: 'User',
      enableSorting: true,
      cell: ({ row }) => (
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', minWidth: 0 }}>
          <Avatar name={row.original.name} src={row.original.avatar_url ?? undefined} size="sm" />
          <div className={styles.cellMain}><strong>{row.original.name}</strong><span>{row.original.email}</span></div>
        </div>
      ),
    },
    { id: 'role', header: 'Platform role', cell: ({ row }) => (row.original.platform_role ? <Badge variant="primary" size="sm">{row.original.platform_role === 'platform.owner' ? 'Owner' : 'Admin'}</Badge> : <span className={styles.muted}>—</span>) },
    {
      id: 'companies',
      header: 'Companies',
      cell: ({ row }) => {
        const ms = row.original.memberships ?? [];
        if (ms.length === 0) return <span className={styles.muted}>No company</span>;
        return (
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
            {ms.map((m) => (
              <Badge key={m.company_id} size="sm" variant={m.is_active ? 'primary' : 'neutral'} title={m.is_active ? undefined : 'Inactive in this company'}>
                {m.company_name ?? `#${m.company_id}`}
                {!m.is_active && ' (inactive)'}
              </Badge>
            ))}
          </div>
        );
      },
    },
    { id: '2fa', header: '2FA', cell: ({ row }) => (row.original.two_factor_enabled ? <Badge size="sm" variant="success">On</Badge> : <Badge size="sm">Off</Badge>) },
    { id: 'last_login_at', accessorKey: 'last_login_at', header: 'Last sign-in', enableSorting: true, cell: ({ row }) => (row.original.last_login_at ? formatRelative(row.original.last_login_at) : 'Never') },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<PlatformUser>
        title="Platform users"
        description="Every account on the platform. Only platform owners can grant platform roles."
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search name or email"
        filters={
          <>
            <Select
              aria-label="Company"
              placeholder="All companies"
              value={list.state.filters.company_id ?? ''}
              onChange={(e) => list.update({ filters: { company_id: e.target.value || undefined } })}
              options={[...(companies.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.name })), { value: 'none', label: 'No company' }]}
            />
            <Select aria-label="Platform role" placeholder="Any role" value={list.state.filters.platform_role ?? ''} onChange={(e) => list.update({ filters: { platform_role: e.target.value || undefined } })} options={[{ value: 'any', label: 'Platform staff' }, { value: 'platform.owner', label: 'Owners' }, { value: 'platform.admin', label: 'Admins' }, { value: 'none', label: 'Regular users' }]} />
            <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
          </>
        }
        rowActions={(u) =>
          u.id === me?.id ? undefined : (
            <>
              {u.is_active ? (
                <MenuItem icon={<UserX size={16} />} destructive onSelect={() => setConfirm({ u, body: { is_active: false }, title: `Deactivate ${u.name}?`, description: 'They are signed out everywhere and cannot sign in to any company.' })}>Deactivate account</MenuItem>
              ) : (
                <MenuItem icon={<UserCheck size={16} />} onSelect={() => update.mutate({ id: u.id, body: { is_active: true } })}>Reactivate account</MenuItem>
              )}
              {isOwner && (
                <>
                  <MenuSeparator />
                  {u.platform_role !== 'platform.admin' && (
                    <MenuItem icon={<ShieldCheck size={16} />} onSelect={() => setConfirm({ u, body: { platform_role: 'platform.admin' }, title: `Make ${u.name} a platform admin?`, description: 'Platform admins can manage companies and users across the platform.' })}>Make platform admin</MenuItem>
                  )}
                  {u.platform_role && (
                    <MenuItem icon={<ShieldOff size={16} />} onSelect={() => setConfirm({ u, body: { platform_role: null }, title: `Remove platform role from ${u.name}?`, description: 'They keep their company memberships.' })}>Remove platform role</MenuItem>
                  )}
                </>
              )}
            </>
          )
        }
      />
      <ConfirmDialog
        open={confirm !== null}
        onClose={() => setConfirm(null)}
        title={confirm?.title ?? ''}
        description={confirm?.description}
        destructive={confirm?.body.is_active === false}
        onConfirm={async () => {
          if (confirm) await update.mutateAsync({ id: confirm.u.id, body: confirm.body });
          setConfirm(null);
        }}
      />
    </>
  );
}

export default function PlatformUsersPage() {
  return (
    <Suspense>
      <PlatformUsersInner />
    </Suspense>
  );
}
