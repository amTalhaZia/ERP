'use client';

import { useEffect, useState, type ReactNode } from 'react';
import Link from 'next/link';
import { Star, UserX } from 'lucide-react';
import { Alert, Avatar, Badge, Button, FormField, Modal, StatusBadge, Textarea, type BadgeVariant } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { humanize } from '@/lib/format';
import type { AttendanceStatus } from './api';
import crud from '@/components/crud/crud.module.scss';
import styles from './hr.module.scss';

/** Avatar + name + code; links to the profile when `href` is given. */
export function EmployeeCell({ name, code, photo, sub, href }: { name: string; code?: string | null; photo?: string | null; sub?: ReactNode; href?: string }) {
  return (
    <div className={styles.person}>
      <Avatar name={name} src={photo ?? undefined} size="sm" />
      <div className={crud.cellMain}>
        <strong>{href ? <Link href={href} onClick={(e) => e.stopPropagation()}>{name}</Link> : name}</strong>
        <span>{sub ?? (code ? <span className={crud.mono}>{code}</span> : null)}</span>
      </div>
    </div>
  );
}

const ATTENDANCE_VARIANTS: Record<AttendanceStatus, BadgeVariant> = {
  present: 'success',
  late: 'warning',
  absent: 'danger',
  half_day: 'info',
  on_leave: 'primary',
  holiday: 'neutral',
  weekend: 'neutral',
};

export function AttendanceBadge({ status }: { status: AttendanceStatus | string }) {
  return <StatusBadge status={status} variant={ATTENDANCE_VARIANTS[status as AttendanceStatus]} label={humanize(status)} />;
}

export function Swatch({ color }: { color: string | null | undefined }) {
  return <span className={styles.swatch} style={color ? { background: color } : undefined} aria-hidden />;
}

export function Stars({ rating, max = 5 }: { rating: number | string | null | undefined; max?: number }) {
  const n = rating === null || rating === undefined || rating === '' ? null : Number(rating);
  if (n === null || Number.isNaN(n)) return <span className={styles.muted}>—</span>;
  return (
    <span className={styles.stars} aria-label={`${n} out of ${max}`} title={`${n} / ${max}`}>
      {Array.from({ length: max }, (_, i) => (
        <Star key={i} size={14} aria-hidden fill={i + 1 <= Math.round(n) ? 'currentColor' : 'none'} />
      ))}
    </span>
  );
}

/** Shown when the signed-in account has no linked employee record (self-service 422). */
export function NotLinkedNotice({ message, compact }: { message?: string | null; compact?: boolean }) {
  return (
    <Alert variant="info" icon={<UserX size={18} />} title={compact ? undefined : 'Your account isn’t linked to an employee record'}>
      {message && compact ? `${message} ` : null}
      Self-service features (check-in/out, leave requests and balances) need your login to be linked to your employee profile. Ask HR to open your
      employee record, choose <strong>Edit</strong>, and select your login under <em>Linked user</em>.
    </Alert>
  );
}

export function ConfidentialBadge() {
  return <Badge size="sm" variant="danger" outline>Confidential</Badge>;
}

/** Modal asking for a required reason (reject verbs → `{ reason }`). */
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

/** Warn before leaving the page while a form has unsaved changes. */
export function useUnsavedChangesWarning(dirty: boolean) {
  useEffect(() => {
    if (!dirty) return;
    const onBeforeUnload = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', onBeforeUnload);
    return () => window.removeEventListener('beforeunload', onBeforeUnload);
  }, [dirty]);
}

export function formatBytes(n: number | null | undefined): string {
  if (!n) return '—';
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

/** Trimmed string or null (for nullable API fields). */
export const nul = (v: string | null | undefined) => (v && v.trim() ? v.trim() : null);
