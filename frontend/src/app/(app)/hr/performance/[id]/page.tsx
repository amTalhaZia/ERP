'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { CheckCheck } from 'lucide-react';
import { Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, PageHeader, SkeletonCard, StatusBadge } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { reviews, useMyEmployee, type PerformanceReview } from '@/features/hr/api';
import { Stars } from '@/features/hr/components';
import { ReviewEditor } from '@/features/hr/Performance';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/hr/hr.module.scss';

function ReadOnly({ r }: { r: PerformanceReview }) {
  return (
    <div className={crud.detailGrid}>
      <div className={styles.stack}>
        <Card>
          <CardHeader title="Criteria" />
          <CardBody padding="none">
            {r.ratings.length === 0 ? <EmptyState size="sm" title="No criteria rated" /> : (
              <div className={crud.tableScroll}>
                <table className={crud.lines}>
                  <thead><tr><th>Criterion</th><th>Rating</th><th>Comment</th></tr></thead>
                  <tbody>
                    {r.ratings.map((x, i) => (
                      <tr key={i}><td>{x.criterion}</td><td><Stars rating={x.rating} /></td><td>{x.comment ?? '—'}</td></tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </CardBody>
        </Card>
        {[['Strengths', r.strengths], ['Areas to improve', r.improvements], ['Goals for next period', r.goals]].map(([title, text]) => (
          <Card key={title}>
            <CardHeader title={title ?? ''} />
            <CardBody><p style={{ whiteSpace: 'pre-wrap', margin: 0 }}>{text || <span className={styles.muted}>Nothing recorded.</span>}</p></CardBody>
          </Card>
        ))}
      </div>
      <Card>
        <CardHeader title="Details" />
        <CardBody>
          <dl className={crud.dl}>
            <dt>Employee</dt><dd>{r.employee ? <Link href={`/hr/employees/${r.employee.id}`}>{r.employee.name}</Link> : '—'}</dd>
            <dt>Reviewer</dt><dd>{r.reviewer?.name ?? '—'}</dd>
            <dt>Cycle</dt><dd className={crud.mono}>{r.cycle}</dd>
            <dt>Period</dt><dd>{formatDate(r.period_start)} – {formatDate(r.period_end)}</dd>
            <dt>Overall</dt><dd><Stars rating={r.overall_rating} /></dd>
            <dt>Submitted</dt><dd>{formatDateTime(r.submitted_at)}</dd>
            <dt>Acknowledged</dt><dd>{formatDateTime(r.acknowledged_at)}</dd>
          </dl>
        </CardBody>
      </Card>
    </div>
  );
}

export default function ReviewPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const can = useCan();
  const user = useAppSelector(selectUser);
  const me = useMyEmployee();
  const q = reviews.useOne(id);
  const acknowledge = reviews.useAction('acknowledge', { success: 'Review acknowledged' });
  const [confirm, setConfirm] = useState<'ack' | null>(null);

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard lines={6} />;
  const r = q.data;
  const isSubject = me.employeeId !== null && me.employeeId === r.employee_id;
  const editable = r.status === 'draft' && can('performance.manage');
  // The reviewed employee acknowledges; the API (policy) is the final judge.
  const canAck = r.status === 'submitted' && (isSubject || (me.employeeId === null && r.reviewer_id !== user?.id && !can('performance.manage')));

  return (
    <div className={styles.stack}>
      <PageHeader
        title={`${r.employee?.name ?? 'Employee'} — ${r.cycle}`}
        description={`${formatDate(r.period_start)} – ${formatDate(r.period_end)} · Reviewer: ${r.reviewer?.name ?? '—'}`}
        backHref="/hr/performance"
        backLabel="Performance"
        meta={<StatusBadge status={r.status} />}
        actions={canAck && <Button leftIcon={<CheckCheck size={16} />} onClick={() => setConfirm('ack')}>Acknowledge</Button>}
      />
      {r.status === 'submitted' && isSubject && <Alert variant="info" title="Please review and acknowledge">Acknowledging confirms you have read this review; it does not mean you agree with every point.</Alert>}
      {r.status === 'draft' && !editable && <Alert variant="info">This review is still a draft.</Alert>}
      {editable ? <ReviewEditor key={r.updated_at ?? r.id} review={r} onSubmitted={() => void q.refetch()} /> : <ReadOnly r={r} />}
      <ConfirmDialog
        open={confirm === 'ack'}
        onClose={() => setConfirm(null)}
        title="Acknowledge this review?"
        description="Confirms you have read it. This can’t be undone."
        confirmLabel="Acknowledge"
        onConfirm={async () => { await acknowledge.mutateAsync({ id: r.id }).catch(() => undefined); setConfirm(null); }}
      />
    </div>
  );
}
