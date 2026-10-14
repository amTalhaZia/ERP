'use client';

import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Alert, ConfirmDialog, FormField, MetricCard, Select, StatusBadge, Textarea } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { useCan } from '@/lib/auth/useCan';
import { receivablesReports, useCompanyId, type DocumentStatus } from './api';
import styles from './receivables.module.scss';

/** Invalidate every AR/AP-related cache (documents, payments, reports, journals). */
export function useInvalidateReceivables() {
  const qc = useQueryClient();
  const keys = ['invoices', 'bills', 'payments', 'expense-claims', 'receivables', 'journal-entries', 'bank-accounts'];
  return () => qc.invalidateQueries({ predicate: (q) => q.queryKey.some((k) => typeof k === 'string' && keys.includes(k)) });
}

/** Status badge; approved / partially paid documents past due show a danger "Overdue" badge too. */
export function DocumentStatusBadge({ status, overdue }: { status: DocumentStatus; overdue?: boolean }) {
  return (
    <span className={styles.inline}>
      <StatusBadge status={status} label={humanize(status)} variant={status === 'pending_approval' ? 'warning' : status === 'partially_paid' ? 'info' : undefined} />
      {overdue && <StatusBadge status="overdue" />}
    </span>
  );
}

/** Branch select (only rendered when the user has more than one branch). */
export function BranchField({ value, onChange, error, disabled }: { value: string; onChange: (v: string) => void; error?: string; disabled?: boolean }) {
  const branches = useAppSelector(selectBranches);
  if (branches.length <= 1) return null;
  return (
    <FormField label="Branch" error={error}>
      <Select value={value} disabled={disabled} placeholder="No branch" onChange={(e) => onChange(e.target.value)} options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
    </FormField>
  );
}

/**
 * Void / reject with a mandatory reason (3–500 chars). Calls `onSubmit(reason)`;
 * on 409 the server message is shown inline with an optional explanation.
 */
export function ReasonDialog({
  open,
  onClose,
  title,
  description,
  confirmLabel,
  onSubmit,
  explain,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: string;
  confirmLabel: string;
  onSubmit: (reason: string) => Promise<unknown>;
  explain?: (e: ApiError) => string | null;
}) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldError, setFieldError] = useState<string | null>(null);
  return (
    <ConfirmDialog
      open={open}
      onClose={onClose}
      title={title}
      description={description}
      destructive
      confirmLabel={confirmLabel}
      onConfirm={async () => {
        setError(null);
        setFieldError(null);
        if (reason.trim().length < 3) {
          setFieldError('Give a reason of at least 3 characters.');
          throw new Error('invalid');
        }
        try {
          await onSubmit(reason.trim());
          onClose();
        } catch (e) {
          const err = e as ApiError;
          if (err.status === 422) setFieldError(err.errors?.reason?.[0] ?? err.message);
          else setError(explain?.(err) ?? err.message);
          throw e;
        }
      }}
    >
      {error && <Alert variant="danger" style={{ marginBottom: 12 }}>{error}</Alert>}
      <FormField label="Reason" required error={fieldError ?? undefined}>
        <Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} />
      </FormField>
    </ConfirmDialog>
  );
}

export const hasPaymentsExplanation = (kind: 'invoice' | 'bill') => (e: ApiError) =>
  e.code === 'finance.has_payments'
    ? `${e.message} Payments allocated to this ${kind} must be voided first (Finance → ${kind === 'invoice' ? 'Receipts' : 'Payments'}), which restores the balance; then the ${kind} can be voided.`
    : null;

/** AR / AP KPI strip from /finance/reports/receivables-summary (reports.financial.view). */
export function ReceivablesSummaryStrip({ side, currency }: { side: 'receivables' | 'payables'; currency: string }) {
  const can = useCan();
  const companyId = useCompanyId();
  const allowed = can('reports.financial.view');
  const q = useQuery({
    queryKey: [companyId, 'receivables', 'summary'],
    queryFn: () => receivablesReports.summary(),
    enabled: allowed,
    staleTime: 30_000,
  });
  if (!allowed) return null;
  if (q.error) return <Alert variant="warning">Could not load the {side} summary.</Alert>;
  const s = q.data?.[side];
  return (
    <div className={styles.summary}>
      <MetricCard loading={q.isLoading} label={side === 'receivables' ? 'Outstanding receivables' : 'Outstanding payables'} value={s ? formatMoney(s.outstanding, currency) : '—'} footer={s ? `${s.open_count} open` : undefined} />
      <MetricCard loading={q.isLoading} label="Overdue" value={s ? formatMoney(s.overdue_amount, currency) : '—'} footer={s ? `${s.overdue_count} past due` : undefined} />
      {side === 'receivables' ? (
        <>
          <MetricCard loading={q.isLoading} label="DSO" value={q.data?.dso_days ? `${q.data.dso_days} days` : '—'} footer="Days sales outstanding (90d)" />
          <MetricCard loading={q.isLoading} label="Avg. days to pay" value={q.data?.average_days_to_pay ? `${q.data.average_days_to_pay} days` : '—'} footer="Paid invoices" />
        </>
      ) : (
        <MetricCard loading={q.isLoading} label="Expense claims payable" value={q.data ? formatMoney(q.data.expense_claims_payable.outstanding, currency) : '—'} footer={q.data ? `${q.data.expense_claims_payable.count} approved` : undefined} />
      )}
    </div>
  );
}
