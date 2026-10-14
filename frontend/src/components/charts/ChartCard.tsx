'use client';

import { useState, type ReactNode } from 'react';
import { BarChart3, Table2 } from 'lucide-react';
import { Card, CardBody, CardHeader, EmptyState, Skeleton } from '@/components/ui';
import styles from './charts.module.scss';

export interface LegendItem {
  label: string;
  color: string;
}

/**
 * Card wrapper every chart uses: title, legend (always for ≥ 2 series),
 * chart ⇄ table toggle (accessible alternative), loading & empty states.
 */
export function ChartCard({
  title,
  description,
  legend,
  loading,
  empty,
  emptyText = 'No data for this period',
  table,
  actions,
  children,
  height = 280,
}: {
  title: string;
  description?: string;
  legend?: LegendItem[];
  loading?: boolean;
  empty?: boolean;
  emptyText?: string;
  /** Rendered in table view (accessibility & exact values). */
  table?: ReactNode;
  actions?: ReactNode;
  children: ReactNode;
  height?: number;
}) {
  const [view, setView] = useState<'chart' | 'table'>('chart');

  return (
    <Card>
      <CardHeader
        title={title}
        description={description}
        actions={
          <div className={styles.headerActions}>
            {actions}
            {table && !empty && !loading && (
              <button
                type="button"
                className={styles.viewToggle}
                onClick={() => setView((v) => (v === 'chart' ? 'table' : 'chart'))}
                aria-label={view === 'chart' ? 'Show as table' : 'Show as chart'}
                title={view === 'chart' ? 'Show as table' : 'Show as chart'}
              >
                {view === 'chart' ? <Table2 size={16} /> : <BarChart3 size={16} />}
              </button>
            )}
          </div>
        }
      />
      <CardBody>
        {legend && legend.length > 1 && !empty && !loading && (
          <ul className={styles.legend} aria-label="Legend">
            {legend.map((l) => (
              <li key={l.label}>
                <span className={styles.swatch} style={{ background: l.color }} aria-hidden />
                {l.label}
              </li>
            ))}
          </ul>
        )}
        {loading ? (
          <Skeleton style={{ height }} />
        ) : empty ? (
          <div style={{ minHeight: height, display: 'grid', placeItems: 'center' }}>
            <EmptyState title={emptyText} description="Records will appear here as they are posted." />
          </div>
        ) : view === 'table' && table ? (
          <div className={styles.tableWrap} style={{ maxHeight: height + 40 }}>
            {table}
          </div>
        ) : (
          <div style={{ height }}>{children}</div>
        )}
      </CardBody>
    </Card>
  );
}
