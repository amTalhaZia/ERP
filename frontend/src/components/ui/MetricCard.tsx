'use client';

import Link from 'next/link';
import { useId, type ComponentPropsWithoutRef, type ReactNode } from 'react';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { cn } from '@/lib/cn';
import { formatNumber, formatPercent } from '@/lib/format';
import { useCountUp } from '@/hooks/useCountUp';
import { Skeleton } from './Skeleton';
import styles from './MetricCard.module.scss';

export interface MetricChange {
  /** Percent change, e.g. 12.5 for +12.5%. */
  value: number;
  direction: 'up' | 'down' | 'flat';
  /** Whether an increase is good (revenue) or bad (churn, overdue). */
  positiveIsGood: boolean;
  /** Comparison label, default "vs previous period". */
  label?: string;
}

export interface MetricCardProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  label: ReactNode;
  /** Numbers animate (count-up); pass a ReactNode for pre-formatted values (money strings). */
  value: ReactNode | number;
  /** Formats the animated number. Default: formatNumber. */
  formatValue?: (n: number) => string;
  previousValue?: ReactNode;
  change?: MetricChange;
  /** Recent values for the inline sparkline (oldest → newest). */
  trend?: number[];
  trendLabel?: string;
  icon?: ReactNode;
  /** Makes the whole card a link. */
  href?: string;
  loading?: boolean;
  /** Render "No data for this period" instead of a value. */
  unavailable?: boolean;
  unavailableText?: string;
  /** Small footer line (e.g. "Updated 5 min ago"). */
  footer?: ReactNode;
}

function AnimatedNumber({ value, format }: { value: number; format: (n: number) => string }) {
  const animated = useCountUp(value);
  // Count-up interpolates fractions; render with the target's precision.
  const decimals = Number.isInteger(value) ? 0 : Math.min(4, (String(value).split('.')[1] ?? '').length);
  const shown = Number.isInteger(value) && Math.abs(animated - value) > 0.5 ? Math.round(animated) : Number(animated.toFixed(decimals));
  return <>{format(shown)}</>;
}

export function Sparkline({ data, label, id, className }: { data: number[]; label?: string; id?: string; className?: string }) {
  const W = 120;
  const H = 32;
  const PAD = 3;
  const n = data.length;
  if (n < 2) return null;
  const min = Math.min(...data);
  const max = Math.max(...data);
  const span = max - min || 1;
  const x = (i: number) => PAD + (i / (n - 1)) * (W - PAD * 2);
  const y = (v: number) => H - PAD - ((v - min) / span) * (H - PAD * 2);
  const points = data.map((v, i) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`);
  const line = points.join(' ');
  const area = `${x(0)},${H - PAD} ${line} ${x(n - 1)},${H - PAD}`;
  const last = data[n - 1];
  const gradientId = `${id ?? 'spark'}-fill`;

  return (
    <svg
      className={cn(styles.sparkline, className)}
      viewBox={`0 0 ${W} ${H}`}
      width={W}
      height={H}
      role="img"
      aria-label={label ?? `Trend over ${n} periods`}
      preserveAspectRatio="none"
    >
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--chart-1)" stopOpacity="0.18" />
          <stop offset="100%" stopColor="var(--chart-1)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <polygon points={area} fill={`url(#${gradientId})`} />
      <polyline points={line} fill="none" stroke="var(--chart-1)" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
      <circle cx={x(n - 1)} cy={y(last)} r={3} fill="var(--chart-1)" stroke="var(--color-surface)" strokeWidth={2} vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

export function MetricCard({
  label,
  value,
  formatValue = (n) => formatNumber(n),
  previousValue,
  change,
  trend,
  trendLabel,
  icon,
  href,
  loading,
  unavailable,
  unavailableText = 'No data for this period',
  footer,
  className,
  ...rest
}: MetricCardProps) {
  const id = useId();

  const tone =
    !change || change.direction === 'flat' ? 'flat' : (change.direction === 'up') === change.positiveIsGood ? 'good' : 'bad';
  const ChangeIcon = change?.direction === 'up' ? ArrowUpRight : change?.direction === 'down' ? ArrowDownRight : Minus;

  const body = (
    <>
      <div className={styles.top}>
        <span className={styles.label}>{label}</span>
        {icon && (
          <span className={styles.icon} aria-hidden>
            {icon}
          </span>
        )}
      </div>

      {loading ? (
        <div className={styles.loading} aria-busy>
          <Skeleton variant="text" width="55%" height={28} />
          <Skeleton variant="text" width="40%" height={12} />
        </div>
      ) : unavailable ? (
        <div className={styles.unavailable}>{unavailableText}</div>
      ) : (
        <>
          <div className={styles.valueRow}>
            <span className={styles.value}>{typeof value === 'number' ? <AnimatedNumber value={value} format={formatValue} /> : value}</span>
            {trend && trend.length > 1 && <Sparkline data={trend} id={id} label={trendLabel} />}
          </div>
          {(change || previousValue) && (
            <div className={styles.meta}>
              {change && (
                <span className={cn(styles.change, styles[tone])}>
                  <ChangeIcon aria-hidden />
                  <span>
                    {change.direction === 'flat'
                      ? formatPercent(0, { fractionDigits: 1 })
                      : formatPercent(Math.abs(change.value), { fractionDigits: 1, signDisplay: 'auto' })}
                  </span>
                  <span className={styles.srOnly}>{change.direction === 'up' ? 'increase' : change.direction === 'down' ? 'decrease' : 'no change'}</span>
                </span>
              )}
              <span className={styles.compare}>
                {previousValue !== undefined ? <>vs {previousValue}</> : null}
                {previousValue !== undefined && change ? ' · ' : null}
                {change ? (change.label ?? 'vs previous period') : null}
              </span>
            </div>
          )}
        </>
      )}

      {footer && <div className={styles.footer}>{footer}</div>}
    </>
  );

  const classes = cn(styles.card, href && styles.interactive, className);
  if (href) {
    return (
      <Link href={href} className={classes}>
        {body}
      </Link>
    );
  }
  return (
    <div className={classes} {...rest}>
      {body}
    </div>
  );
}
