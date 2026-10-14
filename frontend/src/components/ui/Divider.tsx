import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './Divider.module.scss';

export interface DividerProps extends ComponentPropsWithoutRef<'div'> {
  orientation?: 'horizontal' | 'vertical';
  /** Optional centred label ("or", "Advanced"). Horizontal only. */
  label?: ReactNode;
  /** Vertical margin (horizontal) or horizontal margin (vertical). */
  spacing?: 'none' | 'sm' | 'md' | 'lg';
}

export function Divider({ orientation = 'horizontal', label, spacing = 'md', className, ...rest }: DividerProps) {
  if (orientation === 'vertical') {
    return <div role="separator" aria-orientation="vertical" className={cn(styles.vertical, styles[`space-${spacing}`], className)} {...rest} />;
  }
  if (label) {
    return (
      <div role="separator" className={cn(styles.labelled, styles[`space-${spacing}`], className)} {...rest}>
        <span className={styles.label}>{label}</span>
      </div>
    );
  }
  return <hr className={cn(styles.horizontal, styles[`space-${spacing}`], className)} {...(rest as ComponentPropsWithoutRef<'hr'>)} />;
}
