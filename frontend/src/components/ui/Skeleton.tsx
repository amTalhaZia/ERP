import type { ComponentPropsWithoutRef, CSSProperties } from 'react';
import { cn } from '@/lib/cn';
import styles from './Skeleton.module.scss';

export type SkeletonVariant = 'block' | 'text' | 'circle';

export interface SkeletonProps extends ComponentPropsWithoutRef<'span'> {
  variant?: SkeletonVariant;
  width?: number | string;
  height?: number | string;
  /** For `text`: number of lines; the last line is shorter. */
  lines?: number;
  /** Border radius override (block). */
  radius?: number | string;
}

export function Skeleton({ variant = 'block', width, height, lines = 1, radius, className, style, ...rest }: SkeletonProps) {
  const base: CSSProperties = { width, height, borderRadius: radius, ...style };

  if (variant === 'text' && lines > 1) {
    return (
      <span className={cn(styles.lines, className)} aria-hidden {...rest}>
        {Array.from({ length: lines }, (_, i) => (
          <span
            key={i}
            className={cn(styles.skeleton, styles.text)}
            style={{ ...base, width: i === lines - 1 ? '60%' : (width ?? '100%') }}
          />
        ))}
      </span>
    );
  }

  return <span className={cn(styles.skeleton, styles[variant], className)} style={base} aria-hidden {...rest} />;
}

export interface SkeletonTableProps extends ComponentPropsWithoutRef<'div'> {
  rows?: number;
  cols?: number;
  /** Render a header row. */
  header?: boolean;
  dense?: boolean;
}

export function SkeletonTable({ rows = 6, cols = 5, header = true, dense, className, ...rest }: SkeletonTableProps) {
  const widths = ['30%', '20%', '15%', '20%', '15%', '10%', '12%', '18%'];
  return (
    <div className={cn(styles.table, dense && styles.dense, className)} role="status" aria-label="Loading table" aria-busy {...rest}>
      {header && (
        <div className={cn(styles.row, styles.headerRow)}>
          {Array.from({ length: cols }, (_, c) => (
            <span key={c} className={cn(styles.skeleton, styles.text)} style={{ width: widths[c % widths.length], maxWidth: 120 }} />
          ))}
        </div>
      )}
      {Array.from({ length: rows }, (_, r) => (
        <div key={r} className={styles.row}>
          {Array.from({ length: cols }, (_, c) => (
            <span
              key={c}
              className={cn(styles.skeleton, styles.text)}
              style={{ width: `${55 + ((r * 7 + c * 13) % 40)}%`, animationDelay: `${(r * 60) % 400}ms` }}
            />
          ))}
        </div>
      ))}
    </div>
  );
}

export interface SkeletonCardProps extends ComponentPropsWithoutRef<'div'> {
  /** Show an avatar/icon circle. */
  media?: boolean;
  lines?: number;
}

export function SkeletonCard({ media = false, lines = 3, className, ...rest }: SkeletonCardProps) {
  return (
    <div className={cn(styles.card, className)} role="status" aria-label="Loading" aria-busy {...rest}>
      <div className={styles.cardHead}>
        {media && <Skeleton variant="circle" width={36} height={36} />}
        <div className={styles.cardTitle}>
          <Skeleton variant="text" width="45%" height={14} />
          <Skeleton variant="text" width="30%" height={10} />
        </div>
      </div>
      <Skeleton variant="text" lines={lines} height={12} />
    </div>
  );
}
