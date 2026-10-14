'use client';

import { useState, type ComponentPropsWithoutRef, type ReactNode } from 'react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { cn } from '@/lib/cn';
import { IconButton } from './IconButton';
import styles from './Alert.module.scss';

export type AlertVariant = 'info' | 'success' | 'warning' | 'danger';

const ICONS: Record<AlertVariant, typeof Info> = {
  info: Info,
  success: CircleCheck,
  warning: TriangleAlert,
  danger: CircleAlert,
};

export interface AlertProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  variant?: AlertVariant;
  title?: ReactNode;
  /** Replace the default icon, or pass `null` to hide it. */
  icon?: ReactNode | null;
  /** Shows a close button; the alert hides itself and calls `onDismiss`. */
  dismissible?: boolean;
  onDismiss?: () => void;
  /** Buttons / links under the message. */
  actions?: ReactNode;
}

export function Alert({ variant = 'info', title, icon, dismissible, onDismiss, actions, className, children, role, ...rest }: AlertProps) {
  const [dismissed, setDismissed] = useState(false);
  if (dismissed) return null;
  const Icon = ICONS[variant];

  return (
    <div
      role={role ?? (variant === 'danger' || variant === 'warning' ? 'alert' : 'status')}
      className={cn(styles.alert, styles[variant], className)}
      {...rest}
    >
      {icon !== null && <span className={styles.icon}>{icon ?? <Icon aria-hidden />}</span>}
      <div className={styles.body}>
        {title && <div className={styles.title}>{title}</div>}
        {children && <div className={styles.message}>{children}</div>}
        {actions && <div className={styles.actions}>{actions}</div>}
      </div>
      {dismissible && (
        <IconButton
          icon={<X />}
          aria-label="Dismiss"
          size="sm"
          className={styles.close}
          onClick={() => {
            setDismissed(true);
            onDismiss?.();
          }}
        />
      )}
    </div>
  );
}

export interface InlineMessageProps extends ComponentPropsWithoutRef<'span'> {
  variant?: AlertVariant;
  icon?: ReactNode | null;
}

/** Compact icon + text line for field-level or inline feedback. */
export function InlineMessage({ variant = 'info', icon, className, children, ...rest }: InlineMessageProps) {
  const Icon = ICONS[variant];
  return (
    <span role={variant === 'danger' ? 'alert' : undefined} className={cn(styles.inline, styles[variant], className)} {...rest}>
      {icon !== null && <span className={styles.inlineIcon}>{icon ?? <Icon aria-hidden />}</span>}
      <span>{children}</span>
    </span>
  );
}
