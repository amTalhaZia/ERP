'use client';

import { useQuery } from '@tanstack/react-query';
import { Download } from 'lucide-react';
import { Button, Card, CardBody, EmptyState, ErrorState, PageHeader, Skeleton, StatusBadge, useToast } from '@/components/ui';
import { formatDateTime, formatNumber, formatRelative } from '@/lib/format';
import { isApiError } from '@/lib/api/errors';
import { reportsApi } from '@/features/reports/api';
import crud from '@/components/crud/crud.module.scss';

export default function ExportsPage() {
  const toast = useToast();
  const q = useQuery({
    queryKey: ['report-exports'],
    queryFn: reportsApi.jobs,
    // Poll while anything is still running.
    refetchInterval: (query) => (query.state.data?.some((j) => j.status === 'queued' || j.status === 'running') ? 5000 : false),
  });

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 1000 }}>
      <PageHeader title="My exports" description="Large exports are generated in the background. Files are kept for a limited time." backHref="/reports" backLabel="Reports" />
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <Skeleton style={{ height: 240 }} />
      ) : !q.data?.length ? (
        <EmptyState title="No background exports" description="Small exports download immediately and don’t appear here." />
      ) : (
        <Card>
          <CardBody padding="none">
            <div className={crud.tableScroll} style={{ margin: 0 }}>
              <table className={crud.lines}>
                <thead><tr><th>Report</th><th>Format</th><th>Status</th><th className={crud.r}>Rows</th><th>Requested</th><th>Expires</th><th /></tr></thead>
                <tbody>
                  {q.data.map((j) => (
                    <tr key={j.id}>
                      <td>{j.report_name ?? j.report_key}</td>
                      <td>{j.format.toUpperCase()}</td>
                      <td>
                        <StatusBadge status={j.is_expired ? 'expired' : j.status === 'queued' ? 'pending' : j.status === 'running' ? 'processing' : j.status} />
                        {j.error && <div style={{ fontSize: 12, color: 'var(--color-danger-text)' }}>{j.error}</div>}
                      </td>
                      <td className={crud.r}>{j.row_count === null ? '—' : formatNumber(j.row_count)}</td>
                      <td title={formatDateTime(j.created_at)}>{formatRelative(j.created_at)}</td>
                      <td>{j.expires_at ? formatDateTime(j.expires_at) : '—'}</td>
                      <td style={{ textAlign: 'right' }}>
                        {j.download_url && (
                          <Button
                            size="sm"
                            variant="secondary"
                            leftIcon={<Download size={14} />}
                            onClick={() => reportsApi.downloadJob(j).catch((e) => toast.error('Download failed', isApiError(e) ? e.message : undefined))}
                          >
                            Download
                          </Button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
