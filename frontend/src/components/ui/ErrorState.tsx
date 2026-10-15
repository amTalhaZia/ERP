'use client';

import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { CircleAlert, FileQuestion, LayoutDashboard, Lock, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/cn';
import { isApiError, type ApiError } from '@/lib/api/errors';
import { Button } from './Button';
import styles from './ErrorState.module.scss';

export interface ErrorStateProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  title?: ReactNode;
  /** The failure — ApiError.message is shown when available. */
  error?: ApiError | Error | string | null | unknown;
  /** Explicit message overriding `error`. */
  message?: ReactNode;
  onRetry?: () => void;
  retryLabel?: string;
  retrying?: boolean;
  size?: 'sm' | 'md';
}

export function errorMessage(error: unknown): string | undefined {
  if (!error) return undefined;
  if (typeof error === 'string') return error;
  if (isApiError(error)) return error.message;
  if (error instanceof Error) return error.message;
  return undefined;
}

interface StatusStateProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  title?: ReactNode;
  message?: ReactNode;
  size?: 'sm' | 'md';
  /** Hide the "Go to dashboard" link (e.g. when already on the dashboard). */
  hideHomeLink?: boolean;
}

function StatusState({
  icon,
  tone,
  title,
  message,
  size = 'md',
  hideHomeLink,
  className,
  ...rest
}: StatusStateProps & { icon: ReactNode; tone: 'neutral' | 'warning' }) {
  return (
    <div role="alert" className={cn(styles.root, styles[size], className)} {...rest}>
      <div className={cn(styles.icon, styles[tone])} aria-hidden>
        {icon}
      </div>
      <h3 className={styles.title}>{title}</h3>
      {message && <p className={styles.message}>{message}</p>}
      {!hideHomeLink && (
        <div className={styles.actions}>
          <Button href="/dashboard" variant="outline" size={size === 'sm' ? 'sm' : 'md'} leftIcon={<LayoutDashboard />}>
            Go to dashboard
          </Button>
        </div>
      )}
    </div>
  );
}

/** 403 — the user is signed in but lacks the permission. Retrying cannot help. */
export function AccessDeniedState({
  title = 'You don’t have access to this page',
  message = 'Ask your company administrator for the required permission.',
  ...rest
}: StatusStateProps) {
  return <StatusState icon={<Lock />} tone="warning" title={title} message={message} {...rest} />;
}

/** 404 — the record or page does not exist (or is outside the user's scope). */
export function NotFoundState({
  title = 'Not found',
  message = 'This record doesn’t exist or may have been removed.',
  ...rest
}: StatusStateProps) {
  return <StatusState icon={<FileQuestion />} tone="neutral" title={title} message={message} {...rest} />;
}

export function ErrorState({
  title,
  error,
  message,
  onRetry,
  retryLabel = 'Try again',
  retrying,
  size = 'md',
  className,
  ...rest
}: ErrorStateProps) {
  // Permission / missing-record failures get a dedicated, non-retryable state
  // unless the caller supplied its own wording.
  if (isApiError(error) && title === undefined && message === undefined) {
    if (error.status === 403) return <AccessDeniedState size={size} className={className} {...rest} />;
    if (error.status === 404) return <NotFoundState size={size} className={className} {...rest} />;
  }

  const text = message ?? errorMessage(error) ?? 'We could not load this content. Please try again.';
  const code = isApiError(error) ? error.code : undefined;

  return (
    <div role="alert" className={cn(styles.root, styles[size], className)} {...rest}>
      <div className={styles.icon} aria-hidden>
        <CircleAlert />
      </div>
      <h3 className={styles.title}>{title ?? 'Something went wrong'}</h3>
      <p className={styles.message}>{text}</p>
      {code && <code className={styles.code}>{code}</code>}
      {onRetry && (
        <div className={styles.actions}>
          <Button variant="outline" size={size === 'sm' ? 'sm' : 'md'} leftIcon={<RefreshCw />} onClick={onRetry} loading={retrying}>
            {retryLabel}
          </Button>
        </div>
      )}
    </div>
  );
}
