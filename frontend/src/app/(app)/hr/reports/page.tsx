'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { CalendarDays, Clock, UserMinus, UserPlus, Users } from 'lucide-react';
import { Alert, DateInput, ErrorState, MetricCard, PageHeader, Tab, TabList, TabPanel, Tabs } from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { useCan } from '@/lib/auth/useCan';
import { formatNumber, humanize } from '@/lib/format';
import { hrReports, typeLabel, useCompanyId } from '@/features/hr/api';
import { todayInTz, useCompanyTz } from '@/features/hr/time';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/hr/hr.module.scss';

const AXIS = { fill: 'var(--chart-axis)', fontSize: 12 };
const int = (v: number) => formatNumber(v, { maximumFractionDigits: 0 });

/** Horizontal bar chart of one count series, with an accessible table alternative. */
function CountChart({ title, description, rows, loading, valueLabel = 'Employees', format = int }: {
  title: string; description?: string; rows: { label: string; value: number }[]; loading: boolean; valueLabel?: string; format?: (v: number) => string;
}) {
  const height = Math.max(200, Math.min(rows.length, 15) * 32 + 40);
  const total = rows.reduce((a, r) => a + r.value, 0);
  return (
    <ChartCard
      title={title}
      description={description}
      loading={loading}
      empty={!loading && rows.length === 0}
      height={height}
      table={
        <table className={crud.lines}>
          <thead><tr><th>{title.replace(/^.* by /i, '').replace(/^./, (c) => c.toUpperCase())}</th><th className={crud.r}>{valueLabel}</th><th className={crud.r}>Share</th></tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.label}><td>{r.label}</td><td className={crud.r}>{format(r.value)}</td><td className={crud.r}>{total ? `${((r.value / total) * 100).toFixed(1)}%` : '—'}</td></tr>
            ))}
          </tbody>
          <tfoot><tr><td>Total</td><td className={crud.r}>{format(total)}</td><td /></tr></tfoot>
        </table>
      }
    >
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={rows.slice(0, 15).map((r) => ({ label: r.label, [valueLabel]: r.value }))} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
          <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
          <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} tick={AXIS} />
          <YAxis type="category" dataKey="label" width={140} tickLine={false} axisLine={false} tick={AXIS} interval={0} />
          <Tooltip content={<ChartTooltip formatValue={format} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
          <Bar dataKey={valueLabel} fill="var(--chart-1)" radius={[0, 4, 4, 0]} maxBarSize={22} />
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}

function HeadcountTab({ from, to }: { from: string; to: string }) {
  const companyId = useCompanyId();
  const q = useQuery({ queryKey: [companyId, 'hr-reports', 'headcount', from, to], queryFn: () => hrReports.headcount({ from, to }) });
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const d = q.data;
  const loading = q.isLoading;
  return (
    <div className={styles.stack}>
      <div className={styles.metrics}>
        <MetricCard label="Headcount" icon={<Users size={18} />} loading={loading} value={d?.total ?? 0} footer="Currently employed" />
        <MetricCard label="Joiners" icon={<UserPlus size={18} />} loading={loading} value={d?.joiners ?? 0} footer="Hired in the period" />
        <MetricCard label="Leavers" icon={<UserMinus size={18} />} loading={loading} value={d?.leavers ?? 0} footer="Terminated in the period" />
        <MetricCard label="Net change" icon={<Users size={18} />} loading={loading} value={(d?.joiners ?? 0) - (d?.leavers ?? 0)} footer="Joiners minus leavers" />
      </div>
      <div className={styles.grid2}>
        <CountChart title="Headcount by department" loading={loading} rows={(d?.by_department ?? []).map((r) => ({ label: r.name, value: r.total }))} />
        <CountChart title="Headcount by branch" loading={loading} rows={(d?.by_branch ?? []).map((r) => ({ label: r.name, value: r.total }))} />
      </div>
      <div className={styles.grid2}>
        <CountChart title="Headcount by employment type" loading={loading} rows={(d?.by_employment_type ?? []).map((r) => ({ label: typeLabel(r.key), value: r.total }))} />
        <CountChart title="All records by status" description="Includes terminated employees" loading={loading} rows={(d?.by_status ?? []).map((r) => ({ label: humanize(r.key), value: r.total }))} valueLabel="Records" />
      </div>
    </div>
  );
}

const ATT_SERIES = [
  { key: 'present', label: 'Present', color: 'var(--chart-1)' },
  { key: 'late', label: 'Late', color: 'var(--chart-2)' },
  { key: 'half_day', label: 'Half day', color: 'var(--chart-3)' },
  { key: 'on_leave', label: 'On leave', color: 'var(--chart-4)' },
  { key: 'absent', label: 'Absent', color: 'var(--chart-5)' },
] as const;

function AttendanceTab({ from, to }: { from: string; to: string }) {
  const companyId = useCompanyId();
  const q = useQuery({ queryKey: [companyId, 'hr-reports', 'attendance', from, to], queryFn: () => hrReports.attendance({ from, to }) });
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const d = q.data;
  const rows = d?.by_department ?? [];
  const height = Math.max(220, Math.min(rows.length, 15) * 36 + 40);
  const rate = d && d.totals.records ? ((d.totals.present + d.totals.late) / d.totals.records) * 100 : null;
  return (
    <div className={styles.stack}>
      <div className={styles.metrics}>
        <MetricCard label="Records" icon={<CalendarDays size={18} />} loading={q.isLoading} value={d?.totals.records ?? 0} />
        <MetricCard label="Attendance rate" icon={<Users size={18} />} loading={q.isLoading} unavailable={rate === null && !!d} value={rate !== null ? `${rate.toFixed(1)}%` : '—'} footer="Present or late, of all records" />
        <MetricCard label="Late arrivals" icon={<Clock size={18} />} loading={q.isLoading} value={d?.totals.late ?? 0} />
        <MetricCard label="Hours worked" icon={<Clock size={18} />} loading={q.isLoading} value={formatNumber(d?.totals.worked_hours ?? 0, { maximumFractionDigits: 1 })} />
      </div>
      <ChartCard
        title="Attendance by department"
        description="Record counts per status"
        loading={q.isLoading}
        empty={!q.isLoading && rows.length === 0}
        legend={ATT_SERIES.map((s) => ({ label: s.label, color: s.color }))}
        height={height}
        table={
          <table className={crud.lines}>
            <thead>
              <tr><th>Department</th><th className={crud.r}>Employees</th>{ATT_SERIES.map((s) => <th key={s.key} className={crud.r}>{s.label}</th>)}<th className={crud.r}>Hours</th><th className={crud.r}>Late min</th></tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.id ?? 'none'}>
                  <td>{r.name}</td><td className={crud.r}>{r.employees}</td>
                  {ATT_SERIES.map((s) => <td key={s.key} className={crud.r}>{r[s.key]}</td>)}
                  <td className={crud.r}>{formatNumber(r.worked_hours, { maximumFractionDigits: 1 })}</td><td className={crud.r}>{r.late_minutes}</td>
                </tr>
              ))}
            </tbody>
          </table>
        }
      >
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={rows.slice(0, 15).map((r) => ({ label: r.name, ...Object.fromEntries(ATT_SERIES.map((s) => [s.label, r[s.key]])) }))} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
            <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
            <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} tick={AXIS} />
            <YAxis type="category" dataKey="label" width={140} tickLine={false} axisLine={false} tick={AXIS} interval={0} />
            <Tooltip content={<ChartTooltip formatValue={int} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
            {ATT_SERIES.map((s, i) => (
              <Bar key={s.key} dataKey={s.label} stackId="a" fill={s.color} maxBarSize={24} radius={i === ATT_SERIES.length - 1 ? [0, 4, 4, 0] : undefined} />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>
    </div>
  );
}

function LeaveTab({ from, to }: { from: string; to: string }) {
  const companyId = useCompanyId();
  const q = useQuery({ queryKey: [companyId, 'hr-reports', 'leave', from, to], queryFn: () => hrReports.leave({ from, to }) });
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const d = q.data;
  const days = (v: number) => formatNumber(v, { maximumFractionDigits: 1 });
  return (
    <div className={styles.grid2}>
      <CountChart title="Approved leave days by type" description="Requests starting in the period" loading={q.isLoading} valueLabel="Days" format={days}
        rows={(d?.approved_by_type ?? []).map((r) => ({ label: r.name, value: Number(r.days) }))} />
      <CountChart title="Requests by status" loading={q.isLoading} valueLabel="Requests"
        rows={(d?.by_status ?? []).map((r) => ({ label: humanize(r.key), value: r.total }))} />
    </div>
  );
}

export default function HrReportsPage() {
  const can = useCan();
  const tz = useCompanyTz();
  const today = todayInTz(tz);
  const [from, setFrom] = useState(`${today.slice(0, 4)}-01-01`);
  const [to, setTo] = useState(today);
  const [tab, setTab] = useState('headcount');

  if (!can('employees.view')) return <Alert variant="warning">You don’t have permission to view HR reports.</Alert>;
  const valid = !!from && !!to && from <= to;

  return (
    <div className={styles.stack}>
      <PageHeader
        title="HR reports"
        description="Headcount, attendance and leave for a period."
        actions={
          <div className={styles.row}>
            <DateInput aria-label="From" value={from || null} max={to || undefined} onValueChange={(v) => setFrom(v ?? '')} />
            <DateInput aria-label="To" value={to || null} min={from || undefined} onValueChange={(v) => setTo(v ?? '')} />
          </div>
        }
      />
      {!valid ? (
        <Alert variant="warning">Choose both dates, with the start on or before the end.</Alert>
      ) : (
        <Tabs value={tab} onValueChange={setTab}>
          <TabList>
            <Tab value="headcount">Headcount</Tab>
            <Tab value="attendance">Attendance</Tab>
            <Tab value="leave">Leave</Tab>
          </TabList>
          <TabPanel value="headcount"><div className={styles.tabBody}><HeadcountTab from={from} to={to} /></div></TabPanel>
          <TabPanel value="attendance"><div className={styles.tabBody}><AttendanceTab from={from} to={to} /></div></TabPanel>
          <TabPanel value="leave"><div className={styles.tabBody}><LeaveTab from={from} to={to} /></div></TabPanel>
        </Tabs>
      )}
    </div>
  );
}
