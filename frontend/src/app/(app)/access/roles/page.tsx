'use client';

import { Suspense, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Lock, Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, Checkbox, ConfirmDialog, Drawer, EmptyState, ErrorState, FormField, Input, PageHeader, SearchInput, Skeleton, Textarea,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { accessApi, roles, type Role } from '@/features/access/api';

function RoleEditor({ role, open, onClose }: { role: Role | null; open: boolean; onClose: () => void }) {
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const catalogue = useQuery({ queryKey: [companyId, 'permissions'], queryFn: accessApi.permissions, enabled: open, staleTime: 5 * 60_000 });
  const detail = roles.useOne(role?.id, { enabled: open && !!role });
  const create = roles.useCreate({ onSuccess: onClose });
  const update = roles.useUpdate({ onSuccess: onClose });
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [filter, setFilter] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loadedFor, setLoadedFor] = useState<number | 'new' | null>(null);

  // Initialise the editor once the role detail (with permissions) arrives.
  const key = role ? role.id : 'new';
  if (open && loadedFor !== key && (!role || detail.data)) {
    setLoadedFor(key);
    setName(role?.name ?? '');
    setDescription(role?.description ?? '');
    setSelected(new Set(detail.data?.permissions?.map((p) => p.id) ?? []));
    setError(null);
  }

  const locked = !!role?.is_locked;
  const modules = useMemo(() => {
    const term = filter.trim().toLowerCase();
    return (catalogue.data ?? [])
      .map((m) => ({ ...m, permissions: m.permissions.filter((p) => !term || p.slug.includes(term) || p.description.toLowerCase().includes(term)) }))
      .filter((m) => m.permissions.length > 0);
  }, [catalogue.data, filter]);

  const toggle = (id: number, on: boolean) =>
    setSelected((s) => {
      const n = new Set(s);
      if (on) n.add(id);
      else n.delete(id);
      return n;
    });

  const save = async () => {
    setError(null);
    if (!name.trim()) return setError('Role name is required.');
    const body = { name: name.trim(), description: description.trim() || null, permission_ids: [...selected] };
    try {
      if (role) await update.mutateAsync({ id: role.id, body: role.is_system ? { ...body, name: role.name, description: role.description } : body });
      else await create.mutateAsync(body);
    } catch (e) {
      const err = e as ApiError;
      setError(err.errors ? Object.values(err.errors)[0]?.[0] ?? err.message : err.message);
    }
  };

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="lg"
      title={role ? role.name : 'New role'}
      description={`${selected.size} permission${selected.size === 1 ? '' : 's'} selected`}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          {!locked && <Button onClick={save} loading={create.isPending || update.isPending}>{role ? 'Save role' : 'Create role'}</Button>}
        </>
      }
    >
      {locked && <Alert variant="info" icon={<Lock size={16} />} style={{ marginBottom: 16 }}>The Company Owner role always has every permission and cannot be edited.</Alert>}
      {role?.is_system && !locked && <Alert variant="info" style={{ marginBottom: 16 }}>System role: the name is fixed, but you can adjust its permissions.</Alert>}
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
      <div style={{ display: 'grid', gap: 12, marginBottom: 16 }}>
        <FormField label="Name" required>
          <Input value={name} onChange={(e) => setName(e.target.value)} disabled={!!role?.is_system} maxLength={100} />
        </FormField>
        <FormField label="Description">
          <Textarea rows={2} value={description} onChange={(e) => setDescription(e.target.value)} disabled={!!role?.is_system} maxLength={300} />
        </FormField>
      </div>
      <SearchInput placeholder="Filter permissions" value={filter} onValueChange={setFilter} aria-label="Filter permissions" />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12, marginTop: 12 }}>
        {catalogue.isLoading || (role && detail.isLoading) ? (
          <Skeleton style={{ height: 300 }} />
        ) : (
          modules.map((m) => {
            const ids = m.permissions.map((p) => p.id);
            const all = ids.every((id) => selected.has(id));
            const some = ids.some((id) => selected.has(id));
            return (
              <Card key={m.module}>
                <CardHeader
                  title={humanize(m.module)}
                  actions={
                    !locked && (
                      <Checkbox
                        label="All"
                        checked={all}
                        indeterminate={!all && some}
                        onChange={(e) => setSelected((s) => {
                          const n = new Set(s);
                          ids.forEach((id) => (e.target.checked ? n.add(id) : n.delete(id)));
                          return n;
                        })}
                      />
                    )
                  }
                />
                <CardBody>
                  <div style={{ display: 'grid', gap: 8, gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))' }}>
                    {m.permissions.map((p) => (
                      <Checkbox
                        key={p.id}
                        checked={locked || selected.has(p.id)}
                        disabled={locked}
                        onChange={(e) => toggle(p.id, e.target.checked)}
                        label={p.description}
                        description={<code style={{ fontSize: 11 }}>{p.slug}</code>}
                      />
                    ))}
                  </div>
                </CardBody>
              </Card>
            );
          })
        )}
      </div>
    </Drawer>
  );
}

function RolesInner() {
  const can = useCan();
  const q = roles.useList({ per_page: 100, sort: 'name' });
  const del = roles.useDelete();
  const [editing, setEditing] = useState<Role | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Role | null>(null);
  const canManage = can('roles.manage');

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Roles & permissions"
        description="Roles bundle permissions. A user’s access is the union of their roles in this company."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New role</Button>}
      />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <Skeleton style={{ height: 300 }} />
      ) : !q.data?.data.length ? (
        <EmptyState title="No roles" />
      ) : (
        <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' }}>
          {q.data.data.map((r) => (
            <Card key={r.id}>
              <CardHeader
                title={<span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}><ShieldCheck size={16} color="var(--color-primary)" />{r.name}</span>}
                actions={r.is_locked ? <Badge size="sm" variant="neutral"><Lock size={11} /> Locked</Badge> : r.is_system ? <Badge size="sm" variant="neutral">System</Badge> : <Badge size="sm" variant="primary">Custom</Badge>}
              />
              <CardBody>
                <p style={{ fontSize: 13, color: 'var(--color-text-secondary)', minHeight: 38 }}>{r.description ?? 'No description'}</p>
                <div style={{ display: 'flex', gap: 16, fontSize: 13, margin: '12px 0', color: 'var(--color-text-muted)' }}>
                  <span><strong style={{ color: 'var(--color-text)' }}>{r.users_count ?? 0}</strong> users</span>
                  <span><strong style={{ color: 'var(--color-text)' }}>{r.permissions_count ?? 0}</strong> permissions</span>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                  <Button size="sm" variant="secondary" leftIcon={<Pencil size={14} />} onClick={() => setEditing(r)}>{canManage && !r.is_locked ? 'Edit' : 'View'}</Button>
                  {canManage && !r.is_system && (
                    <Button size="sm" variant="ghost" leftIcon={<Trash2 size={14} />} onClick={() => setDeleting(r)} disabled={(r.users_count ?? 0) > 0} title={(r.users_count ?? 0) > 0 ? 'Remove users from this role first' : undefined}>
                      Delete
                    </Button>
                  )}
                </div>
              </CardBody>
            </Card>
          ))}
        </div>
      )}
      <RoleEditor key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} role={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete role “${deleting?.name}”?`}
        destructive
        confirmLabel="Delete role"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id);
          setDeleting(null);
        }}
      />
    </div>
  );
}

export default function RolesPage() {
  return (
    <Suspense>
      <RolesInner />
    </Suspense>
  );
}
