'use client';

import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { CircleAlert, RefreshCw } from 'lucide-react';
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

export function ErrorState({
  title = 'Something went wrong',
  error,
  message,
  onRetry,
  retryLabel = 'Try again',
  retrying,
  size = 'md',
  className,
  ...rest
}: ErrorStateProps) {
  const text = message ?? errorMessage(error) ?? 'We could not load this content. Please try again.';
  const code = isApiError(error) ? error.code : undefined;

  return (
    <div role="alert" className={cn(styles.root, styles[size], className)} {...rest}>
      <div className={styles.icon} aria-hidden>
        <CircleAlert />
      </div>
      <h3 className={styles.title}>{title}</h3>
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
