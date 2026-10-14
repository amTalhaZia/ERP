'use client';

import styles from './charts.module.scss';

interface Entry {
  name?: string | number;
  value?: number | string | (number | string)[];
  color?: string;
  dataKey?: string | number;
}

/** Recharts custom tooltip: text in ink tokens, a colored swatch carries identity. */
export function ChartTooltip({
  active,
  payload,
  label,
  formatValue = (v) => String(v),
  formatLabel = (l) => String(l),
}: {
  active?: boolean;
  payload?: Entry[];
  label?: string | number;
  formatValue?: (v: number) => string;
  formatLabel?: (l: string | number) => string;
}) {
  if (!active || !payload?.length) return null;
  return (
    <div className={styles.tooltip} role="status">
      {label !== undefined && <div className={styles.tooltipLabel}>{formatLabel(label)}</div>}
      <ul>
        {payload.map((p) => (
          <li key={String(p.dataKey ?? p.name)}>
            <span className={styles.swatch} style={{ background: p.color }} aria-hidden />
            <span className={styles.tooltipName}>{p.name}</span>
            <span className={styles.tooltipValue}>{formatValue(Number(p.value))}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}
