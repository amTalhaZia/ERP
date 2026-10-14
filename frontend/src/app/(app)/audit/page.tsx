'use client';

import { Suspense, useState } from 'react';
import { DateInput, Drawer, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { formatDateTime, humanize } from '@/lib/format';
import { auditLogs, type AuditLog } from '@/features/access/api';
import styles from '@/components/crud/crud.module.scss';

const ACTION_GROUPS = [
  ['auth.', 'Sign-in & security'],
  ['users.', 'Users'],
  ['roles.', 'Roles & permissions'],
  ['companies.', 'Company settings'],
  ['employees.', 'Employees'],
  ['journals.', 'Financial postings'],
  ['inventory.', 'Inventory'],
  ['reports.', 'Exports'],
] as const;

function Diff({ log }: { log: AuditLog }) {
  const keys = Array.from(new Set([...Object.keys(log.old_values ?? {}), ...Object.keys(log.new_values ?? {})]));
  if (keys.length === 0) return <p className={styles.muted}>No field changes recorded.</p>;
  const show = (v: unknown) => (v === null || v === undefined ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));
  return (
    <div className={styles.tableScroll}>
      <table className={styles.lines}>
        <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
        <tbody>
          {keys.map((k) => (
            <tr key={k}>
              <td className={styles.mono}>{k}</td>
              <td style={{ color: 'var(--color-danger-text)' }}>{show(log.old_values?.[k])}</td>
              <td style={{ color: 'var(--color-success-text)' }}>{show(log.new_values?.[k])}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function AuditInner() {
  const list = useListState({ sort: '-created_at' });
  const query = auditLogs.useList(list.apiParams);
  const [open, setOpen] = useState<AuditLog | null>(null);

  const columns: DataTableColumn<AuditLog>[] = [
    { id: 'created_at', accessorKey: 'created_at', header: 'When', enableSorting: true, cell: ({ row }) => <span className={styles.num}>{formatDateTime(row.original.created_at)}</span> },
    { id: 'actor', header: 'Actor', cell: ({ row }) => row.original.actor?.name ?? row.original.actor_name ?? <span className={styles.muted}>System</span> },
    { id: 'action', accessorKey: 'action', header: 'Action', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.action}</span> },
    { id: 'entity', header: 'Record', cell: ({ row }) => row.original.entity_label ?? (row.original.entity_type ? `${humanize(row.original.entity_type.split('\\').pop() ?? '')} #${row.original.entity_id}` : '—') },
    { id: 'ip', header: 'IP', cell: ({ row }) => <span className={styles.mono}>{row.original.ip_address ?? '—'}</span> },
  ];

  return (
    <>
      <ListPage<AuditLog>
        title="Audit log"
        description="Append-only record of security events, configuration changes, postings and exports. Entries cannot be edited or deleted."
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search action or record"
        filters={
          <>
            <Select aria-label="Category" placeholder="All categories" value={list.state.filters.action ?? ''} onChange={(e) => list.update({ filters: { action: e.target.value || undefined } })} options={ACTION_GROUPS.map(([v, l]) => ({ value: v, label: l }))} />
            <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
            <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          </>
        }
        emptyTitle="No audit entries"
        onRowClick={setOpen}
      />
      <Drawer open={open !== null} onClose={() => setOpen(null)} title={open?.action} description={open ? formatDateTime(open.created_at) : undefined} size="md">
        {open && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
            <dl className={styles.dl}>
              <dt>Actor</dt><dd>{open.actor?.name ?? open.actor_name ?? 'System'}</dd>
              <dt>Record</dt><dd>{open.entity_label ?? '—'} {open.entity_type && <span className={styles.muted}>({open.entity_type.split('\\').pop()} #{open.entity_id})</span>}</dd>
              <dt>IP address</dt><dd className={styles.mono}>{open.ip_address ?? '—'}</dd>
              <dt>User agent</dt><dd style={{ fontSize: 12 }}>{open.user_agent ?? '—'}</dd>
            </dl>
            <div>
              <h3 style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>Changes</h3>
              <Diff log={open} />
            </div>
            {open.context && Object.keys(open.context).length > 0 && (
              <div>
                <h3 style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>Context</h3>
                <pre style={{ fontSize: 12, background: 'var(--color-bg-subtle)', padding: 12, borderRadius: 8, overflow: 'auto' }}>{JSON.stringify(open.context, null, 2)}</pre>
              </div>
            )}
          </div>
        )}
      </Drawer>
    </>
  );
}

export default function AuditPage() {
  return (
    <Suspense>
      <AuditInner />
    </Suspense>
  );
}
