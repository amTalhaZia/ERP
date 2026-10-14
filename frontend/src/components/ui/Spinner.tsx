import type { ComponentPropsWithoutRef } from 'react';
import { cn } from '@/lib/cn';
import styles from './Spinner.module.scss';

export type SpinnerSize = 'xs' | 'sm' | 'md' | 'lg';

export interface SpinnerProps extends Omit<ComponentPropsWithoutRef<'span'>, 'children'> {
  size?: SpinnerSize | number;
  /** Accessible label. Pass `null` to mark decorative (e.g. inside a Button that already says "Saving"). */
  label?: string | null;
}

export function Spinner({ size = 'md', label = 'Loading', className, style, ...rest }: SpinnerProps) {
  const numeric = typeof size === 'number';
  return (
    <span
      role={label === null ? undefined : 'status'}
      aria-label={label ?? undefined}
      aria-hidden={label === null ? true : undefined}
      className={cn(styles.spinner, !numeric && styles[size], className)}
      style={numeric ? { width: size, height: size, ...style } : style}
      {...rest}
    />
  );
}
