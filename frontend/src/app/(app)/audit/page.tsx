'use client';

import { Suspense, useState } from 'react';
import { DateInput, Drawer, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { formatDateTime, humanize } from '@/lib/format';
import { auditLogs, type AuditLog } from '@/features/access/api';
import { auditActionLabel } from '@/lib/auditLabels';
import styles from '@/components/crud/crud.module.scss';

const ACTION_GROUPS = [
  ['auth.', 'Sign-ins & security'],
  ['users.', 'Users & access'],
  ['roles.', 'Roles'],
  ['companies.', 'Company settings'],
  ['employees.', 'Employees'],
  ['leave.', 'Leave'],
  ['holidays.', 'Holidays'],
  ['payroll.', 'Salaries (payroll)'],
  ['invoices.', 'Customer invoices'],
  ['bills.', 'Supplier bills'],
  ['journals.', 'Accounting entries'],
  ['inventory.', 'Stock'],
  ['reports.', 'Downloads & exports'],
] as const;

const who = (l: AuditLog) => l.actor?.name ?? l.actor_name ?? 'System (automatic)';
// "App\Models\HR\PerformanceReview" → "Performance review #1" when the record has no friendlier name.
const record = (l: AuditLog) => {
  if (l.entity_label) return l.entity_label;
  if (!l.entity_type) return '—';
  const words = (l.entity_type.split('\\').pop() ?? '').replace(/([a-z])([A-Z])/g, '$1 $2').toLowerCase();
  return `${words.charAt(0).toUpperCase()}${words.slice(1)} #${l.entity_id}`;
};

function Diff({ log }: { log: AuditLog }) {
  const keys = Array.from(new Set([...Object.keys(log.old_values ?? {}), ...Object.keys(log.new_values ?? {})]));
  if (keys.length === 0) return <p className={styles.muted}>No details were changed.</p>;
  const show = (v: unknown) => (v === null || v === undefined ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));
  return (
    <div className={styles.tableScroll}>
      <table className={styles.lines}>
        <thead><tr><th>What</th><th>Before</th><th>After</th></tr></thead>
        <tbody>
          {keys.map((k) => (
            <tr key={k}>
              <td title={k}>{humanize(k)}</td>
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
    { id: 'actor', header: 'Who', cell: ({ row }) => (row.original.actor || row.original.actor_name ? who(row.original) : <span className={styles.muted}>{who(row.original)}</span>) },
    {
      id: 'action',
      accessorKey: 'action',
      header: 'What happened',
      enableSorting: true,
      cell: ({ row }) => <span title={row.original.action}>{auditActionLabel(row.original.action)}</span>,
    },
    { id: 'entity', header: 'On which record', cell: ({ row }) => record(row.original) },
    { id: 'ip', header: 'Internet address (IP)', cell: ({ row }) => <span className={styles.mono}>{row.original.ip_address ?? '—'}</span> },
  ];

  return (
    <>
      <ListPage<AuditLog>
        title="Audit log"
        description="A permanent history of who did what, and when — sign-ins, changes, approvals and downloads. Nobody can edit or delete it."
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search by record name"
        filters={
          <>
            <Select aria-label="Category" placeholder="Everything" value={list.state.filters.action ?? ''} onChange={(e) => list.update({ filters: { action: e.target.value || undefined } })} options={ACTION_GROUPS.map(([v, l]) => ({ value: v, label: l }))} />
            <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
            <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
          </>
        }
        emptyTitle="Nothing recorded yet"
        onRowClick={setOpen}
      />
      <Drawer open={open !== null} onClose={() => setOpen(null)} title={open ? auditActionLabel(open.action) : undefined} description={open ? `${who(open)} · ${formatDateTime(open.created_at)}` : undefined} size="md">
        {open && (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
            <dl className={styles.dl}>
              <dt>Who</dt><dd>{who(open)}</dd>
              <dt>What happened</dt><dd>{auditActionLabel(open.action)}</dd>
              <dt>On which record</dt><dd>{record(open)}</dd>
              <dt>Internet address</dt><dd className={styles.mono}>{open.ip_address ?? '—'}</dd>
              <dt>Device / browser</dt><dd style={{ fontSize: 12 }}>{open.user_agent ?? '—'}</dd>
              <dt>Technical code</dt><dd className={styles.mono} style={{ fontSize: 12 }}>{open.action}</dd>
            </dl>
            <div>
              <h3 style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>What changed</h3>
              <Diff log={open} />
            </div>
            {open.context && Object.keys(open.context).length > 0 && (
              <div>
                <h3 style={{ fontSize: 13, fontWeight: 600, marginBottom: 8 }}>Extra details (technical)</h3>
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
