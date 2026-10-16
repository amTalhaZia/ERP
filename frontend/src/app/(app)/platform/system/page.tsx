'use client';

import { Fragment, useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Building2, Database, HardDrive, Info, RefreshCw, Users } from 'lucide-react';
import { Alert, Badge, Button, Card, CardBody, CardHeader, ErrorState, MetricCard, PageHeader, SearchInput, Skeleton } from '@/components/ui';
import { formatDateTime, formatNumber } from '@/lib/format';
import { platformApi } from '@/features/platform/api';
import styles from '@/components/crud/crud.module.scss';
import sys from './system.module.scss';

function bytes(n: number | null | undefined) {
  if (n === null || n === undefined) return '—';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  let i = 0;
  let v = n;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return `${v.toFixed(v < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
}

export default function SystemPage() {
  const q = useQuery({ queryKey: ['platform', 'system'], queryFn: platformApi.system });
  const usage = useQuery({ queryKey: ['platform', 'usage'], queryFn: () => platformApi.usage() });
  const qc = useQueryClient();
  const [refreshing, setRefreshing] = useState(false);
  const refresh = async () => {
    setRefreshing(true);
    try {
      qc.setQueryData(['platform', 'usage'], await platformApi.usage(true));
      await q.refetch();
    } finally {
      setRefreshing(false);
    }
  };
  const companies = usage.data?.companies ?? [];
  const maxUsed = Math.max(1, ...companies.map((c) => c.db_bytes + c.file_bytes));
  const [filter, setFilter] = useState('');
  const tables = useMemo(
    () => (q.data?.tables ?? []).filter((t) => !filter || t.name.includes(filter.toLowerCase())).sort((a, b) => (b.size_bytes ?? 0) - (a.size_bytes ?? 0)),
    [q.data, filter],
  );
  const totalSize = (q.data?.tables ?? []).reduce((s, t) => s + (t.size_bytes ?? 0), 0);

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const d = q.data;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="System"
        description={d ? `Snapshot taken ${formatDateTime(d.generated_at)}` : 'Platform health and database overview'}
        actions={<Button variant="secondary" leftIcon={<RefreshCw size={16} />} onClick={() => void refresh()} loading={refreshing || q.isFetching}>Refresh</Button>}
      />
      <Alert variant="info" icon={<Info size={16} />}>
        This page is the platform’s health check: how many companies and users exist, how big the database is, how much disk is left, and which company uses how much space.
        It only shows counts and sizes — never the companies’ actual records.
      </Alert>
      {d?.app.debug && d.app.environment === 'production' && <Alert variant="danger" title="Debug mode is on in production">Set APP_DEBUG=false immediately.</Alert>}
      {d && !d.database.reachable && <Alert variant="danger">The database is not reachable.</Alert>}

      <div style={{ display: 'grid', gap: 16, gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' }}>
        <MetricCard label="Companies" icon={<Building2 size={18} />} loading={!d} value={d?.counts.companies ?? 0} footer={d ? `${d.counts.active_companies} active` : undefined} />
        <MetricCard label="Users" icon={<Users size={18} />} loading={!d} value={d?.counts.users ?? 0} footer={d ? `${d.counts.active_users} active · ${d.counts.platform_users} platform staff` : undefined} />
        <MetricCard label="Database size" icon={<Database size={18} />} loading={!d} value={bytes(totalSize)} footer={d ? `${d.tables.length} tables` : undefined} />
        <MetricCard label="Free disk space" icon={<HardDrive size={18} />} loading={!d} value={bytes(d?.storage.path_free_bytes)} footer={d?.storage.path_total_bytes ? `of ${bytes(d.storage.path_total_bytes)}` : undefined} />
      </div>

      <Card>
        <CardHeader
          title="Storage by company"
          description={`How much space each company’s data takes. Database size is estimated from row counts; files are uploaded documents.${usage.data ? ` Updated ${formatDateTime(usage.data.generated_at)}.` : ''}`}
        />
        <CardBody padding="none">
          {usage.error ? (
            <div style={{ padding: 16 }}><ErrorState error={usage.error} onRetry={() => void usage.refetch()} /></div>
          ) : !usage.data ? (
            <ul className={sys.usage} aria-busy aria-label="Loading storage">
              {[0, 1, 2].map((i) => (
                <li key={i}>
                  <div className={sys.usageHead}>
                    <Skeleton variant="text" width={160 - i * 20} height={12} />
                    <Skeleton variant="text" width={56} height={12} style={{ marginLeft: 'auto' }} />
                  </div>
                  <Skeleton variant="text" height={8} radius={999} width={`${90 - i * 25}%`} />
                  <Skeleton variant="text" width={260} height={10} />
                </li>
              ))}
            </ul>
          ) : (
            <ul className={sys.usage}>
              {companies.map((c) => {
                const used = c.db_bytes + c.file_bytes;
                return (
                  <li key={c.id}>
                    <div className={sys.usageHead}>
                      <Link href={`/platform/companies/${c.id}`} className={sys.usageName}>{c.name}</Link>
                      {c.is_demo && <Badge size="sm" variant="warning">Demo</Badge>}
                      {!c.is_active && <Badge size="sm" variant="neutral">Inactive</Badge>}
                      {c.archived && <Badge size="sm" variant="neutral">Archived</Badge>}
                      <strong className={sys.usageTotal}>{bytes(used)}</strong>
                    </div>
                    <div className={sys.bar} role="img" aria-label={`${bytes(used)} used`}>
                      <span className={sys.barDb} style={{ width: `${(c.db_bytes / maxUsed) * 100}%` }} />
                      <span className={sys.barFiles} style={{ width: `${(c.file_bytes / maxUsed) * 100}%` }} />
                    </div>
                    <div className={sys.usageMeta}>
                      <span><i className={sys.dotDb} /> Database {bytes(c.db_bytes)} · {formatNumber(c.rows)} records</span>
                      <span><i className={sys.dotFiles} /> Files {bytes(c.file_bytes)}</span>
                      {c.tables.length > 0 && <span className={sys.top}>Most records: {c.tables.slice(0, 3).map((x) => `${x.name} (${formatNumber(x.rows)})`).join(', ')}</span>}
                    </div>
                  </li>
                );
              })}
              <li className={sys.shared}>Shared system tables (sessions, cache, jobs, permissions…): <strong>{bytes(usage.data.shared_bytes)}</strong></li>
            </ul>
          )}
        </CardBody>
      </Card>

      <div className={styles.detailGrid}>
        <Card>
          <CardHeader
            title="Database tables"
            description="Approximate row counts and on-disk size (data + indexes). Read-only — there is no SQL console."
            actions={<SearchInput placeholder="Filter tables" value={filter} onValueChange={setFilter} aria-label="Filter tables" size="sm" />}
          />
          <CardBody padding="none">
            {!d ? <Skeleton style={{ height: 300, margin: 16, width: 'auto' }} /> : (
              <div className={styles.tableScroll} style={{ maxHeight: 520, overflowY: 'auto' }}>
                <table className={styles.lines}>
                  <thead><tr><th>Table</th><th className={styles.r}>Rows (approx.)</th><th className={styles.r}>Size</th></tr></thead>
                  <tbody>
                    {tables.map((t) => (
                      <tr key={t.name}>
                        <td className={styles.mono}>{t.name}</td>
                        <td className={styles.r}>{t.rows === null ? '—' : formatNumber(t.rows)}</td>
                        <td className={styles.r}>{bytes(t.size_bytes)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Runtime" />
          <CardBody>
            {!d ? <Skeleton variant="text" lines={10} /> : (
              <dl className={styles.dl}>
                <dt>Application</dt><dd>{d.app.name} {d.app.version}</dd>
                <dt>Environment</dt><dd><Badge size="sm" variant={d.app.environment === 'production' ? 'success' : 'warning'}>{d.app.environment}</Badge>{d.app.debug && <Badge size="sm" variant="danger" style={{ marginLeft: 6 }}>debug</Badge>}</dd>
                <dt>Framework</dt><dd>Laravel {d.app.framework}</dd>
                <dt>PHP</dt><dd>{d.php.version} · {d.php.memory_limit} · upload {d.php.upload_max_filesize}</dd>
                <dt>Database</dt><dd>{d.database.driver} {d.database.version ?? ''}</dd>
                {Object.entries(d.services).map(([k, v]) => (
                  <Fragment key={k}><dt style={{ textTransform: 'capitalize' }}>{k}</dt><dd>{v}</dd></Fragment>
                ))}
                <dt>Time zone</dt><dd>{d.app.timezone}</dd>
                <dt>Maintenance</dt><dd>{d.app.maintenance ? 'On' : 'Off'}</dd>
              </dl>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}
