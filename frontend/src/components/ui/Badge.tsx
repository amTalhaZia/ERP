import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { humanize } from '@/lib/format';
import styles from './Badge.module.scss';

export type BadgeVariant = 'neutral' | 'success' | 'warning' | 'danger' | 'info' | 'primary';
export type BadgeSize = 'sm' | 'md';

export interface BadgeProps extends ComponentPropsWithoutRef<'span'> {
  variant?: BadgeVariant;
  size?: BadgeSize;
  /** Leading status dot. */
  dot?: boolean;
  icon?: ReactNode;
  /** Outlined style instead of soft fill. */
  outline?: boolean;
}

export function Badge({ variant = 'neutral', size = 'md', dot, icon, outline, className, children, ...rest }: BadgeProps) {
  return (
    <span className={cn(styles.badge, styles[variant], styles[size], outline && styles.outline, className)} {...rest}>
      {dot && <span className={styles.dot} aria-hidden />}
      {icon && <span className={styles.icon}>{icon}</span>}
      {children}
    </span>
  );
}

// ---------------------------------------------------------------------------
// StatusBadge — maps domain statuses to variants + human labels.
// ---------------------------------------------------------------------------

export type KnownStatus =
  | 'draft'
  | 'submitted'
  | 'pending_approval'
  | 'confirmed'
  | 'ordered'
  | 'partially_received'
  | 'partially_delivered'
  | 'delivered'
  | 'in_transit'
  | 'reversed'
  | 'accepted'
  | 'expired'
  | 'converted'
  | 'won'
  | 'lost'
  | 'probation'
  | 'terminated'
  | 'suspended'
  | 'on_leave'
  | 'todo'
  | 'blocked'
  | 'done'
  | 'in_review'
  | 'planning'
  | 'acknowledged'
  | 'pending'
  | 'approved'
  | 'rejected'
  | 'paid'
  | 'partially_paid'
  | 'unpaid'
  | 'overdue'
  | 'active'
  | 'inactive'
  | 'cancelled'
  | 'posted'
  | 'void'
  | 'in_progress'
  | 'completed'
  | 'on_hold'
  | 'low_stock'
  | 'out_of_stock'
  | 'in_stock'
  | 'open'
  | 'closed'
  | 'sent'
  | 'received'
  | 'processing'
  | 'failed'
  | 'success'
  | 'archived';

export const STATUS_MAP: Record<KnownStatus, { variant: BadgeVariant; label: string }> = {
  draft: { variant: 'neutral', label: 'Draft' },
  submitted: { variant: 'warning', label: 'Submitted' },
  pending_approval: { variant: 'warning', label: 'Pending approval' },
  confirmed: { variant: 'success', label: 'Confirmed' },
  ordered: { variant: 'info', label: 'Ordered' },
  partially_received: { variant: 'warning', label: 'Partially received' },
  partially_delivered: { variant: 'warning', label: 'Partially delivered' },
  delivered: { variant: 'success', label: 'Delivered' },
  in_transit: { variant: 'info', label: 'In transit' },
  reversed: { variant: 'neutral', label: 'Reversed' },
  accepted: { variant: 'success', label: 'Accepted' },
  expired: { variant: 'neutral', label: 'Expired' },
  converted: { variant: 'primary', label: 'Converted' },
  won: { variant: 'success', label: 'Won' },
  lost: { variant: 'danger', label: 'Lost' },
  probation: { variant: 'info', label: 'Probation' },
  terminated: { variant: 'neutral', label: 'Terminated' },
  suspended: { variant: 'danger', label: 'Suspended' },
  on_leave: { variant: 'warning', label: 'On leave' },
  todo: { variant: 'neutral', label: 'To do' },
  blocked: { variant: 'danger', label: 'Blocked' },
  done: { variant: 'success', label: 'Done' },
  in_review: { variant: 'info', label: 'In review' },
  planning: { variant: 'neutral', label: 'Planning' },
  acknowledged: { variant: 'success', label: 'Acknowledged' },
  pending: { variant: 'warning', label: 'Pending' },
  approved: { variant: 'success', label: 'Approved' },
  rejected: { variant: 'danger', label: 'Rejected' },
  paid: { variant: 'success', label: 'Paid' },
  partially_paid: { variant: 'warning', label: 'Partially paid' },
  unpaid: { variant: 'neutral', label: 'Unpaid' },
  overdue: { variant: 'danger', label: 'Overdue' },
  active: { variant: 'success', label: 'Active' },
  inactive: { variant: 'neutral', label: 'Inactive' },
  cancelled: { variant: 'neutral', label: 'Cancelled' },
  posted: { variant: 'info', label: 'Posted' },
  void: { variant: 'danger', label: 'Void' },
  in_progress: { variant: 'info', label: 'In progress' },
  completed: { variant: 'success', label: 'Completed' },
  on_hold: { variant: 'warning', label: 'On hold' },
  low_stock: { variant: 'warning', label: 'Low stock' },
  out_of_stock: { variant: 'danger', label: 'Out of stock' },
  in_stock: { variant: 'success', label: 'In stock' },
  open: { variant: 'info', label: 'Open' },
  closed: { variant: 'neutral', label: 'Closed' },
  sent: { variant: 'info', label: 'Sent' },
  received: { variant: 'success', label: 'Received' },
  processing: { variant: 'info', label: 'Processing' },
  failed: { variant: 'danger', label: 'Failed' },
  success: { variant: 'success', label: 'Success' },
  archived: { variant: 'neutral', label: 'Archived' },
};

export interface StatusBadgeProps extends Omit<BadgeProps, 'variant' | 'children'> {
  /** Known statuses get a variant + label automatically; unknown strings fall back to neutral + humanised text. */
  status: KnownStatus | (string & {});
  /** Override the displayed label. */
  label?: ReactNode;
  /** Override the variant for a one-off. */
  variant?: BadgeVariant;
}

export function resolveStatus(status: string): { variant: BadgeVariant; label: string } {
  const key = status.toLowerCase().replace(/[\s-]+/g, '_') as KnownStatus;
  return STATUS_MAP[key] ?? { variant: 'neutral', label: humanize(status) };
}

export function StatusBadge({ status, label, variant, dot = true, ...rest }: StatusBadgeProps) {
  const resolved = resolveStatus(status);
  return (
    <Badge variant={variant ?? resolved.variant} dot={dot} {...rest}>
      {label ?? resolved.label}
    </Badge>
  );
}
