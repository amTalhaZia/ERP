'use client';

import { useMemo } from 'react';
import Link from 'next/link';
import { CalendarHeart } from 'lucide-react';
import { Card, CardBody, CardHeader, EmptyState, Skeleton } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { holidays, type Holiday } from './api';
import { todayInTz, useCompanyTz } from './time';
import styles from './UpcomingHolidays.module.scss';

const DAY = 86_400_000;

/** Next date a holiday falls on (recurring ones roll forward to this or next year), or null if it has passed. */
function nextOccurrence(h: Holiday, today: string): string | null {
  if (!h.is_recurring) return h.date >= today ? h.date : null;
  const md = h.date.slice(5);
  const year = Number(today.slice(0, 4));
  const thisYear = `${year}-${md}`;
  return thisYear >= today ? thisYear : `${year + 1}-${md}`;
}

/**
 * The next few holidays for everyone in the company — so staff without HR
 * access can still see when the office is closed. Branch holidays only show
 * to people who work in that branch.
 */
export function UpcomingHolidays({ limit = 5 }: { limit?: number }) {
  const can = useCan();
  const tz = useCompanyTz();
  const branches = useAppSelector(selectBranches);
  const q = holidays.useList({ per_page: 100, sort: 'date' });

  const upcoming = useMemo(() => {
    const today = todayInTz(tz);
    const mine = new Set(branches.map((b) => b.id));
    return (q.data?.data ?? [])
      .filter((h) => h.branch_id === null || mine.has(h.branch_id))
      .map((h) => ({ h, on: nextOccurrence(h, today) }))
      .filter((x): x is { h: Holiday; on: string } => x.on !== null)
      .sort((a, b) => a.on.localeCompare(b.on))
      .slice(0, limit)
      .map(({ h, on }) => ({ h, on, inDays: Math.round((Date.parse(on) - Date.parse(today)) / DAY) }));
  }, [q.data, branches, tz, limit]);

  return (
    <Card>
      <CardHeader
        title={<span className={styles.title}><CalendarHeart size={16} aria-hidden /> Upcoming holidays</span>}
        actions={can('shifts.manage') ? <Link href="/hr/shifts" className={styles.more}>Manage</Link> : undefined}
      />
      <CardBody padding="none">
        {q.isLoading ? (
          <Skeleton style={{ height: 120, margin: 16, width: 'auto' }} />
        ) : upcoming.length === 0 ? (
          <EmptyState size="sm" title="No upcoming holidays" description={can('shifts.manage') ? 'Add them under HR → Shifts & holidays.' : undefined} />
        ) : (
          <ul className={styles.list}>
            {upcoming.map(({ h, on, inDays }) => {
              const d = new Date(`${on}T00:00:00`);
              return (
                <li key={`${h.id}-${on}`}>
                  <span className={styles.date} aria-hidden>
                    <strong>{d.getDate()}</strong>
                    <span>{d.toLocaleDateString(undefined, { month: 'short' })}</span>
                  </span>
                  <span className={styles.what}>
                    <strong>{h.name}</strong>
                    <span>
                      {d.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}
                      {h.branch?.name ? ` · ${h.branch.name} only` : ''}
                    </span>
                  </span>
                  <span className={styles.when} data-soon={inDays <= 7 ? 'true' : undefined}>
                    {inDays === 0 ? 'Today' : inDays === 1 ? 'Tomorrow' : `in ${inDays} days`}
                  </span>
                </li>
              );
            })}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}
