'use client';

import { useState } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CalendarPlus, Lock, Unlock } from 'lucide-react';
import { Alert, Button, Card, CardBody, ConfirmDialog, EmptyState, ErrorState, PageHeader, Skeleton, StatusBadge, Textarea, useToast } from '@/components/ui';
import { api } from '@/lib/api/client';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { periods, type FinancialPeriod } from '@/features/finance/api';
import styles from '@/components/crud/crud.module.scss';

export default function PeriodsPage() {
  const can = useCan();
  const toast = useToast();
  const qc = useQueryClient();
  const q = periods.useList({ per_page: 100, sort: '-start_date' });
  const close = periods.useAction('close', { success: 'Period closed' });
  const reopen = periods.useAction<{ reason?: string }>('reopen', { success: 'Period reopened' });
  const newYear = useMutation({
    mutationFn: () => api.post('/financial-years', {}),
    onSuccess: () => {
      void qc.invalidateQueries({ predicate: (x) => x.queryKey.includes('financial-periods') });
      toast.success('Financial year created with 12 periods');
    },
    onError: (e: ApiError) => toast.error('Could not create year', e.errors ? Object.values(e.errors)[0]?.[0] : e.message),
  });
  const [confirm, setConfirm] = useState<{ p: FinancialPeriod; action: 'close' | 'reopen' } | null>(null);
  const [reason, setReason] = useState('');
  const manage = can('periods.manage');

  const groups = (q.data?.data ?? []).reduce<Record<string, FinancialPeriod[]>>((acc, p) => {
    const k = p.financial_year?.name ?? 'Year';
    (acc[k] ??= []).push(p);
    return acc;
  }, {});

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Financial periods"
        description="Postings are only allowed into open periods. Close periods in order once books are reconciled."
        actions={manage && <Button variant="secondary" leftIcon={<CalendarPlus size={16} />} loading={newYear.isPending} onClick={() => newYear.mutate()}>Add next financial year</Button>}
      />
      {q.error ? <ErrorState error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <Skeleton style={{ height: 300 }} /> : !q.data?.data.length ? (
        <EmptyState title="No financial periods" description="Create a financial year to start posting." />
      ) : (
        Object.entries(groups).map(([year, ps]) => (
          <Card key={year}>
            <CardBody padding="none">
              <div className={styles.tableScroll}>
                <table className={styles.lines}>
                  <thead><tr><th colSpan={5}>{year}</th></tr><tr><th>Period</th><th>Dates</th><th>Status</th><th>Closed</th><th /></tr></thead>
                  <tbody>
                    {ps.map((p) => (
                      <tr key={p.id}>
                        <td><strong>{p.name}</strong></td>
                        <td>{formatDate(p.start_date)} – {formatDate(p.end_date)}</td>
                        <td><StatusBadge status={p.status === 'open' ? 'active' : 'closed'} label={p.status === 'open' ? 'Open' : 'Closed'} /></td>
                        <td className={styles.muted}>{p.closed_at ? `${formatDateTime(p.closed_at)} · ${p.closed_by?.name ?? ''}` : '—'}</td>
                        <td style={{ textAlign: 'right' }}>
                          {manage && (p.status === 'open' ? (
                            <Button size="sm" variant="ghost" leftIcon={<Lock size={14} />} onClick={() => setConfirm({ p, action: 'close' })}>Close</Button>
                          ) : (
                            <Button size="sm" variant="ghost" leftIcon={<Unlock size={14} />} onClick={() => setConfirm({ p, action: 'reopen' })}>Reopen</Button>
                          ))}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardBody>
          </Card>
        ))
      )}
      <ConfirmDialog
        open={confirm !== null}
        onClose={() => { setConfirm(null); setReason(''); }}
        title={confirm?.action === 'close' ? `Close ${confirm.p.name}?` : `Reopen ${confirm?.p.name}?`}
        description={confirm?.action === 'close' ? 'No further postings will be accepted for these dates. Earlier periods must already be closed.' : 'Reopening allows changes to closed books. This is recorded in the audit log.'}
        destructive={confirm?.action === 'reopen'}
        confirmLabel={confirm?.action === 'close' ? 'Close period' : 'Reopen period'}
        onConfirm={async () => {
          if (!confirm) return;
          try {
            if (confirm.action === 'close') await close.mutateAsync({ id: confirm.p.id });
            else await reopen.mutateAsync({ id: confirm.p.id, body: { reason } });
          } finally {
            setConfirm(null);
            setReason('');
          }
        }}
      >
        {confirm?.action === 'reopen' && <Textarea aria-label="Reason" placeholder="Reason (recorded in audit log)" value={reason} onChange={(e) => setReason(e.target.value)} rows={2} />}
        {confirm?.action === 'close' && <Alert variant="info">Tip: run the trial balance before closing.</Alert>}
      </ConfirmDialog>
    </div>
  );
}
