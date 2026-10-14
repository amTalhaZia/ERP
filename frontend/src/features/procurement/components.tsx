'use client';

import { useState, type ReactNode } from 'react';
import { Check, Star, X } from 'lucide-react';
import { Alert, Badge, Button, FormField, Modal, StatusBadge, Textarea } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { formatDateTime, humanize } from '@/lib/format';
import { PRIORITY_VARIANTS, STATUS_VARIANTS, type RequisitionPriority } from './api';
import styles from './procurement.module.scss';

/** StatusBadge with procurement-specific variants (submitted, ordered, partially received…). */
export function DocStatus({ status }: { status: string }) {
  return <StatusBadge status={status} variant={STATUS_VARIANTS[status]} label={STATUS_VARIANTS[status] ? humanize(status) : undefined} />;
}

export function PriorityBadge({ priority }: { priority: RequisitionPriority }) {
  return (
    <Badge variant={PRIORITY_VARIANTS[priority] ?? 'neutral'} size="sm">
      {humanize(priority)}
    </Badge>
  );
}

export function Stars({ rating }: { rating: number | null }) {
  if (!rating) return <span style={{ color: 'var(--color-text-muted)' }}>—</span>;
  return (
    <span className={styles.stars} aria-label={`${rating} out of 5`} title={`${rating} / 5`}>
      {[1, 2, 3, 4, 5].map((n) => (
        <Star key={n} size={14} aria-hidden fill={n <= rating ? 'currentColor' : 'none'} />
      ))}
    </span>
  );
}

/** Modal asking for a required reason (reject / cancel verbs → `{ reason }`). */
export function ReasonDialog({
  open,
  onClose,
  title,
  description,
  confirmLabel,
  destructive = true,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: ReactNode;
  confirmLabel: string;
  destructive?: boolean;
  onSubmit: (reason: string) => Promise<unknown>;
}) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [pending, setPending] = useState(false);

  const submit = async () => {
    if (!reason.trim()) {
      setError('A reason is required.');
      return;
    }
    setPending(true);
    setError(null);
    try {
      await onSubmit(reason.trim());
      onClose();
    } catch (e) {
      const err = e as ApiError;
      setError(err.errors?.reason?.[0] ?? err.message);
    } finally {
      setPending(false);
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      description={description}
      size="sm"
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button variant={destructive ? 'danger' : 'primary'} onClick={submit} loading={pending}>{confirmLabel}</Button>
        </>
      }
    >
      <FormField label="Reason" required error={error ?? undefined}>
        <Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
      </FormField>
    </Modal>
  );
}

export interface TimelineStep {
  label: string;
  at?: string | null;
  detail?: ReactNode;
  state: 'done' | 'pending' | 'bad';
}

export function Timeline({ steps }: { steps: TimelineStep[] }) {
  return (
    <ol className={styles.timeline}>
      {steps.map((s) => (
        <li key={s.label}>
          <span className={styles.dot} data-state={s.state} aria-hidden>
            {s.state === 'done' ? <Check size={12} /> : s.state === 'bad' ? <X size={12} /> : null}
          </span>
          <div className={styles.stepText}>
            <strong>{s.label}</strong>
            <span>
              {s.at ? formatDateTime(s.at) : s.state === 'pending' ? 'Pending' : ''}
              {s.detail ? <> · {s.detail}</> : null}
            </span>
          </div>
        </li>
      ))}
    </ol>
  );
}

/** Banner for 409 / generic errors. */
export function ErrorBanner({ message }: { message: string | null }) {
  if (!message) return null;
  return <Alert variant="danger">{message}</Alert>;
}
