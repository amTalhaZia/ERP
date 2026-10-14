'use client';

import { useMemo, useState } from 'react';
import { CalendarDays, Clock, Pencil, Plus, Repeat, Trash2 } from 'lucide-react';
import {
  Alert, Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, IconButton, PageHeader, Select, SkeletonCard,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import { holidays, shifts, type Holiday, type Shift } from '@/features/hr/api';
import { HolidayForm, ShiftForm, WEEKDAYS } from '@/features/hr/SimpleForms';
import { minutesLabel } from '@/features/hr/time';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/hr/hr.module.scss';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

function shiftLength(s: Shift) {
  const [sh, sm] = s.start_time.split(':').map(Number);
  const [eh, em] = s.end_time.split(':').map(Number);
  let mins = (eh! * 60 + em!) - (sh! * 60 + sm!);
  if (mins <= 0) mins += 24 * 60;
  return Math.max(0, mins - s.break_minutes);
}

function ShiftsCard() {
  const q = shifts.useList({ per_page: 100, sort: 'name' });
  const del = shifts.useDelete();
  const [editing, setEditing] = useState<Shift | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Shift | null>(null);
  const rows = q.data?.data ?? [];

  return (
    <Card>
      <CardHeader title="Shifts" description="Working hours and days. Assign shifts from an employee's profile." actions={<Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>New shift</Button>} />
      <CardBody padding="none">
        {q.error ? <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} /> : q.isLoading ? <div style={{ padding: 16 }}><SkeletonCard lines={3} /></div> : rows.length === 0 ? (
          <EmptyState size="sm" icon={<Clock size={20} />} title="No shifts yet" description="Without shifts, Monday–Friday is treated as the working week." action={<Button size="sm" onClick={() => setEditing('new')}>Create a shift</Button>} />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Name</th><th>Hours</th><th>Break</th><th>Net</th><th>Days</th><th className={crud.r}>Assigned</th><th aria-label="Actions" /></tr></thead>
              <tbody>
                {rows.map((s) => (
                  <tr key={s.id}>
                    <td><strong>{s.name}</strong> {s.is_default && <Badge size="sm" variant="primary">Default</Badge>}</td>
                    <td className={crud.num}>{s.start_time}–{s.end_time}{s.end_time <= s.start_time ? ' (+1)' : ''}</td>
                    <td>{minutesLabel(s.break_minutes)}</td>
                    <td>{minutesLabel(shiftLength(s))}</td>
                    <td>
                      <span className={styles.dayChips} aria-label={WEEKDAYS.filter((d) => s.days_of_week.includes(d.value)).map((d) => d.label).join(', ')}>
                        {WEEKDAYS.map((d) => <span key={d.value} data-on={s.days_of_week.includes(d.value)} aria-hidden>{d.short}</span>)}
                      </span>
                    </td>
                    <td className={crud.r}>{s.assignments_count ?? 0}</td>
                    <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                      <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${s.name}`} onClick={() => setEditing(s)} />
                      <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${s.name}`} onClick={() => setDeleting(s)} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
      <ShiftForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : `${editing.id}-${editing.updated_at ?? ''}`} open={editing !== null} row={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'shift'}?`}
        description="Shifts with employee assignments can’t be deleted."
        destructive
        confirmLabel="Delete shift"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

function HolidaysCard() {
  const branches = useAppSelector(selectBranches);
  const thisYear = new Date().getFullYear();
  const [year, setYear] = useState(thisYear);
  const [branch, setBranch] = useState('');
  // Fixed-date holidays in the year, plus recurring ones (stored once, repeat every year).
  const fixed = holidays.useList({ from: `${year}-01-01`, to: `${year}-12-31`, per_page: 100, sort: 'date', ...(branch ? { 'filter[branch_id]': branch } : {}) });
  const recurring = holidays.useList({ 'filter[is_recurring]': 'true', per_page: 100, sort: 'date', ...(branch ? { 'filter[branch_id]': branch } : {}) });
  const del = holidays.useDelete();
  const [editing, setEditing] = useState<Holiday | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Holiday | null>(null);

  const byMonth = useMemo(() => {
    const seen = new Set<number>();
    const items: { h: Holiday; md: string }[] = [];
    for (const h of [...(fixed.data?.data ?? []), ...(recurring.data?.data ?? [])]) {
      if (seen.has(h.id)) continue;
      seen.add(h.id);
      items.push({ h, md: h.date.slice(5) });
    }
    items.sort((a, b) => a.md.localeCompare(b.md));
    const groups = new Map<number, typeof items>();
    for (const it of items) {
      const m = Number(it.md.slice(0, 2)) - 1;
      groups.set(m, [...(groups.get(m) ?? []), it]);
    }
    return [...groups.entries()].sort((a, b) => a[0] - b[0]);
  }, [fixed.data, recurring.data]);
  const loading = fixed.isLoading || recurring.isLoading;
  const error = fixed.error ?? recurring.error;

  return (
    <Card>
      <CardHeader
        title="Public holidays"
        description="Holidays are excluded from leave day counts. Branch holidays apply only to that branch."
        actions={
          <div className={styles.row}>
            <Select aria-label="Year" size="sm" value={String(year)} onChange={(e) => setYear(Number(e.target.value))}
              options={[thisYear - 1, thisYear, thisYear + 1].map((y) => ({ value: String(y), label: String(y) }))} />
            <Select aria-label="Branch" size="sm" value={branch} onChange={(e) => setBranch(e.target.value)} placeholder="All branches"
              options={branches.map((b) => ({ value: String(b.id), label: b.name }))} />
            <Button size="sm" leftIcon={<Plus size={14} />} onClick={() => setEditing('new')}>New holiday</Button>
          </div>
        }
      />
      <CardBody>
        {error ? <ErrorState size="sm" error={error} onRetry={() => { void fixed.refetch(); void recurring.refetch(); }} /> : loading ? <SkeletonCard lines={4} /> : byMonth.length === 0 ? (
          <EmptyState size="sm" icon={<CalendarDays size={20} />} title={`No holidays in ${year}`} action={<Button size="sm" onClick={() => setEditing('new')}>Add a holiday</Button>} />
        ) : (
          <div className={styles.months}>
            {byMonth.map(([m, items]) => (
              <section key={m} aria-label={`${MONTHS[m]} ${year}`}>
                <h4 style={{ margin: '0 0 4px', fontSize: 13, fontWeight: 600 }}>{MONTHS[m]}</h4>
                <ul className={styles.monthList}>
                  {items.map(({ h, md }) => {
                    const shown = `${year}-${md}`;
                    return (
                      <li key={h.id}>
                        <span className={styles.dayNum}><strong>{md.slice(3)}</strong><span>{formatDate(shown, 'EEE')}</span></span>
                        <span className={styles.grow}>
                          <span style={{ display: 'block', fontWeight: 500 }}>{h.name}</span>
                          <span className={styles.small}>
                            {h.branch?.name ?? 'All branches'}
                            {h.is_recurring && <> · <Repeat size={11} aria-hidden /> every year{h.date.slice(0, 4) !== String(year) ? ` (since ${h.date.slice(0, 4)})` : ''}</>}
                          </span>
                        </span>
                        <IconButton size="sm" icon={<Pencil size={14} />} aria-label={`Edit ${h.name}`} onClick={() => setEditing(h)} />
                        <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${h.name}`} onClick={() => setDeleting(h)} />
                      </li>
                    );
                  })}
                </ul>
              </section>
            ))}
          </div>
        )}
      </CardBody>
      <HolidayForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} row={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.name ?? 'holiday'}?`}
        destructive
        confirmLabel="Delete holiday"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </Card>
  );
}

export default function ShiftsPage() {
  const can = useCan();
  if (!can('shifts.manage')) return <Alert variant="warning">You don’t have permission to manage shifts and holidays.</Alert>;
  return (
    <div className={styles.stack}>
      <PageHeader title="Shifts & holidays" description="Working patterns drive late and worked-time calculations; holidays are skipped when counting leave days." />
      <ShiftsCard />
      <HolidaysCard />
    </div>
  );
}
