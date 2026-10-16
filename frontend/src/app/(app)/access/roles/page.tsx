'use client';

import { Suspense, useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ChevronRight, Lock, Plus, Trash2, Users } from 'lucide-react';
import {
  Alert, Button, Checkbox, ConfirmDialog, Drawer, EmptyState, ErrorState, FormField, Input, PageHeader, SearchInput, Select, Skeleton, Textarea,
} from '@/components/ui';
import { cn } from '@/lib/cn';
import { useCan } from '@/lib/auth/useCan';
import { humanize } from '@/lib/format';
import { get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { accessApi, roles, type Role } from '@/features/access/api';
import { AREA_LABELS, groupRoles, roleIcon } from '@/features/access/roleLevels';
import styles from './roles.module.scss';

function RoleEditor({ role, open, onClose, allRoles }: { role: Role | null; open: boolean; onClose: () => void; allRoles: Role[] }) {
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
  const [copying, setCopying] = useState(false);
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
      .map((m) => ({
        ...m,
        permissions: m.permissions.filter(
          (p) => !term || p.description.toLowerCase().includes(term) || (AREA_LABELS[m.module]?.label ?? m.module).toLowerCase().includes(term),
        ),
      }))
      .filter((m) => m.permissions.length > 0);
  }, [catalogue.data, filter]);

  const setMany = (ids: number[], on: boolean) =>
    setSelected((s) => {
      const n = new Set(s);
      ids.forEach((id) => (on ? n.add(id) : n.delete(id)));
      return n;
    });

  // "Start from an existing role" — copies its access so people tweak rather than build from zero.
  const copyFrom = async (id: string) => {
    if (!id) return;
    setCopying(true);
    try {
      const r = await get<{ data: Role }>(`/roles/${id}`);
      setSelected(new Set(r.data.permissions?.map((p) => p.id) ?? []));
    } finally {
      setCopying(false);
    }
  };

  const save = async () => {
    setError(null);
    if (!name.trim()) return setError('Please give the role a name.');
    if (selected.size === 0) return setError('Tick at least one thing this role can do.');
    const body = { name: name.trim(), description: description.trim() || null, permission_ids: [...selected] };
    try {
      if (role) await update.mutateAsync({ id: role.id, body: role.is_system ? { ...body, name: role.name, description: role.description } : body });
      else await create.mutateAsync(body);
    } catch (e) {
      const err = e as ApiError;
      setError(err.errors ? Object.values(err.errors)[0]?.[0] ?? err.message : err.message);
    }
  };

  const count = locked ? (catalogue.data ?? []).reduce((n, m) => n + m.permissions.length, 0) : selected.size;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="lg"
      title={role ? role.name : 'Create a new role'}
      description={role ? `Can do ${count} thing${count === 1 ? '' : 's'}` : 'Name the job, then tick what this person is allowed to do.'}
      footer={
        <>
          <span className={styles.footCount}>{count} selected</span>
          <Button variant="secondary" onClick={onClose}>{locked ? 'Close' : 'Cancel'}</Button>
          {!locked && <Button onClick={save} loading={create.isPending || update.isPending}>{role ? 'Save changes' : 'Create role'}</Button>}
        </>
      }
    >
      {locked && (
        <Alert variant="info" icon={<Lock size={16} />} title="This role is protected" style={{ marginBottom: 16 }}>
          The Company Owner can always do everything, so nobody — not even an owner — can remove access from it by mistake and get locked out.
          To change what other people can do, close this and open their role (for example Company Administrator), then choose <b>Change access</b>.
        </Alert>
      )}
      {role?.is_system && !locked && <Alert variant="info" style={{ marginBottom: 16 }}>This is a built-in role, so its name stays the same — but you can change what it is allowed to do.</Alert>}
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}

      {!locked && (
        <div className={styles.editorTop}>
          <FormField label="Role name" required hint={!role ? 'The job title, e.g. “Store Keeper” or “Cashier”.' : undefined}>
            <Input value={name} onChange={(e) => setName(e.target.value)} disabled={!!role?.is_system} maxLength={100} placeholder="e.g. Store Keeper" />
          </FormField>
          <FormField label="What does this person do?">
            <Textarea rows={2} value={description} onChange={(e) => setDescription(e.target.value)} disabled={!!role?.is_system} maxLength={300} placeholder="e.g. Receives goods and keeps the stock count correct." />
          </FormField>
          <FormField label="Start from an existing role" hint="Optional — copies its access, then you can tick or untick.">
            <Select
              aria-label="Copy access from"
              placeholder={copying ? 'Copying…' : 'Choose a role to copy'}
              value=""
              disabled={copying}
              onChange={(ev) => void copyFrom(ev.target.value)}
              options={allRoles.filter((r) => !r.is_locked && r.id !== role?.id).map((r) => ({ value: String(r.id), label: r.name }))}
            />
          </FormField>
        </div>
      )}

      <h3 className={styles.editorHeading}>{locked ? 'Everything this role can do' : 'What can they do?'}</h3>
      <SearchInput placeholder="Search, e.g. invoices, leave, stock" value={filter} onValueChange={setFilter} aria-label="Search what this role can do" />
      <div className={styles.areas}>
        {catalogue.isLoading || (role && detail.isLoading) ? (
          <Skeleton style={{ height: 300 }} />
        ) : modules.length === 0 ? (
          <EmptyState size="sm" title="Nothing matches your search" />
        ) : (
          modules.map((m) => {
            const ids = m.permissions.map((p) => p.id);
            const on = locked ? ids.length : ids.filter((id) => selected.has(id)).length;
            const area = AREA_LABELS[m.module];
            const Icon = area?.icon;
            return (
              <section key={m.module} className={cn(styles.area, on > 0 && styles.areaOn)}>
                <header className={styles.areaHead}>
                  {Icon && <span className={styles.areaIcon} aria-hidden><Icon size={16} /></span>}
                  <div className={styles.areaTitle}>
                    <strong>{area?.label ?? humanize(m.module)}</strong>
                    {area?.hint && <span>{area.hint}</span>}
                  </div>
                  <span className={styles.areaCount}>{on}/{ids.length}</span>
                  {!locked && (
                    <Checkbox
                      label="All"
                      checked={on === ids.length}
                      indeterminate={on > 0 && on < ids.length}
                      onChange={(e) => setMany(ids, e.target.checked)}
                    />
                  )}
                </header>
                <div className={styles.perms}>
                  {m.permissions.map((p) => (
                    <Checkbox
                      key={p.id}
                      checked={locked || selected.has(p.id)}
                      disabled={locked}
                      onChange={(e) => setMany([p.id], e.target.checked)}
                      label={p.description}
                      title={p.slug}
                    />
                  ))}
                </div>
              </section>
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
  const list = useMemo(() => q.data?.data ?? [], [q.data]);
  const groups = useMemo(() => groupRoles(list), [list]);
  const most = Math.max(1, ...list.map((r) => r.permissions_count ?? 0));

  return (
    <div className={styles.page}>
      <PageHeader
        title="Roles & permissions"
        description="A role is a job type. It decides what a person can see and do. Give each person the role that matches their job."
        actions={canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New role</Button>}
      />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <div className={styles.grid}>{Array.from({ length: 6 }, (_, i) => <Skeleton key={i} style={{ height: 150 }} />)}</div>
      ) : list.length === 0 ? (
        <EmptyState title="No roles yet" />
      ) : (
        groups.map((g) => (
          <section key={g.key} className={cn(styles.group, styles[g.tone])}>
            <header className={styles.groupHead}>
              <span className={styles.dot} aria-hidden />
              <h2>{g.title}</h2>
              <span>{g.hint}</span>
            </header>
            <div className={styles.grid}>
              {g.items.map((r) => {
                const Icon = roleIcon(r.slug);
                const people = r.users_count ?? 0;
                const things = r.permissions_count ?? 0;
                const editable = canManage && !r.is_locked;
                return (
                  <article key={r.id} className={styles.card}>
                    <button type="button" className={styles.cardMain} onClick={() => setEditing(r)} aria-label={`${editable ? 'Change' : 'See'} what ${r.name} can do`}>
                      <span className={styles.cardTop}>
                        <span className={styles.icon} aria-hidden><Icon size={18} /></span>
                        <span className={styles.tag}>
                          {r.is_locked ? <><Lock size={11} /> Can’t be changed</> : r.is_system ? 'Built-in' : 'Made by you'}
                        </span>
                      </span>
                      <span className={styles.name}>{r.name}</span>
                      <span className={styles.desc}>{r.description ?? 'No description yet'}</span>
                      <span className={styles.meter} aria-label={`Can do ${things} things`}>
                        <span className={styles.meterLabel}>
                          <span>Access</span>
                          <span>{things} {things === 1 ? 'thing' : 'things'}</span>
                        </span>
                        <span className={styles.bar}><span style={{ width: `${Math.max(4, (things / most) * 100)}%` }} /></span>
                      </span>
                    </button>
                    <footer className={styles.cardFoot}>
                      <span className={styles.people}><Users size={14} /> {people} {people === 1 ? 'person' : 'people'}</span>
                      {canManage && !r.is_system && (
                        <button
                          type="button"
                          className={styles.iconBtn}
                          onClick={() => setDeleting(r)}
                          disabled={people > 0}
                          title={people > 0 ? 'Move these people to another role first' : 'Delete role'}
                          aria-label={`Delete ${r.name}`}
                        >
                          <Trash2 size={14} />
                        </button>
                      )}
                      <button type="button" className={styles.open} onClick={() => setEditing(r)}>
                        {editable ? 'Change access' : 'See access'} <ChevronRight size={14} />
                      </button>
                    </footer>
                  </article>
                );
              })}
            </div>
          </section>
        ))
      )}
      <RoleEditor
        key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id}
        open={editing !== null}
        role={editing === 'new' ? null : editing}
        allRoles={list}
        onClose={() => setEditing(null)}
      />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete the “${deleting?.name}” role?`}
        description="Nobody has this role right now, so nothing else changes."
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
