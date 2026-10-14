'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, CardBody, CardHeader, DateInput, EmptyState, ErrorState, FormField, MetricCard, Select, SkeletonCard } from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { formatCompact, formatDate, formatMoney } from '@/lib/format';
import { kindLabel, payrollApi, payrollRuns, useCompanyCurrency, useCompanyId, type ComponentKind } from './api';
import { RunStatusBadge, periodLabel, todayIso } from './components';
import crud from '@/components/crud/crud.module.scss';
import styles from './payroll.module.scss';

const AXIS = { fill: 'var(--chart-axis)', fontSize: 12 };
const yearStart = () => `${todayIso().slice(0, 4)}-01-01`;

function SummaryReport() {
  const companyId = useCompanyId();
  const currency = useCompanyCurrency();
  const [from, setFrom] = useState(yearStart);
  const [to, setTo] = useState(todayIso);
  const valid = !!from && !!to && from <= to;
  const q = useQuery({
    queryKey: [companyId, 'payroll-reports', 'summary', from, to],
    queryFn: () => payrollApi.summary(from, to),
    enabled: valid,
  });
  const d = q.data;
  const loading = q.isLoading && valid;
  const money = (v: string | number) => formatMoney(v, currency);
  const deptRows = (d?.departments ?? []).map((r) => ({ label: r.department, Gross: Number(r.gross), Net: Number(r.net) }));

  return (
    <div className={styles.stack}>
      <div className={styles.filters}>
        <FormField label="Period end from"><DateInput value={from} onValueChange={(v) => setFrom(v ?? '')} /></FormField>
        <FormField label="to"><DateInput value={to} onValueChange={(v) => setTo(v ?? '')} /></FormField>
        <span className={styles.hint}>Only finalized and paid runs are included.</span>
      </div>
      {!valid && <p className={styles.danger}>Choose a valid date range.</p>}
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : (
        <>
          <div className={styles.metrics}>
            <MetricCard loading={loading} label="Gross pay" value={d ? money(d.totals.gross) : '—'} footer={d ? `${d.totals.runs} run${d.totals.runs === 1 ? '' : 's'}` : undefined} />
            <MetricCard loading={loading} label="Tax withheld" value={d ? money(d.totals.tax) : '—'} />
            <MetricCard loading={loading} label="Deductions" value={d ? money(d.totals.deductions) : '—'} footer="Including tax" />
            <MetricCard loading={loading} label="Employer contributions" value={d ? money(d.totals.employer_contributions) : '—'} />
            <MetricCard loading={loading} label="Net pay" value={d ? money(d.totals.net) : '—'} footer={d ? `Average ${money(d.totals.average_net)} per payslip` : undefined} />
            <MetricCard loading={loading} label="Employees paid" value={d?.totals.headcount_paid ?? '—'} footer={d ? `${d.totals.payslips} payslips` : undefined} />
          </div>

          <ChartCard
            title="Gross and net pay by department"
            loading={loading}
            empty={!loading && deptRows.length === 0}
            emptyText="No released payroll in this period"
            height={Math.max(220, Math.min(deptRows.length, 15) * 44 + 40)}
            legend={[{ label: 'Gross', color: 'var(--chart-1)' }, { label: 'Net', color: 'var(--chart-2)' }]}
            table={
              <div className={crud.tableScroll}>
                <table className={crud.lines} style={{ minWidth: 720 }}>
                  <thead><tr><th>Department</th><th className={crud.r}>Employees</th><th className={crud.r}>Gross</th><th className={crud.r}>Tax</th><th className={crud.r}>Deductions</th><th className={crud.r}>Employer</th><th className={crud.r}>Net</th></tr></thead>
                  <tbody>
                    {(d?.departments ?? []).map((r) => (
                      <tr key={r.department_id ?? 'none'}>
                        <td>{r.department}</td>
                        <td className={crud.r}>{r.employees}</td>
                        <td className={crud.r}>{money(r.gross)}</td>
                        <td className={crud.r}>{money(r.tax)}</td>
                        <td className={crud.r}>{money(r.deductions)}</td>
                        <td className={crud.r}>{money(r.employer_contributions)}</td>
                        <td className={crud.r}>{money(r.net)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            }
          >
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={deptRows.slice(0, 15)} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
                <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
                <XAxis type="number" tickLine={false} axisLine={false} tick={AXIS} tickFormatter={(v: number) => formatCompact(v)} />
                <YAxis type="category" dataKey="label" width={140} tickLine={false} axisLine={false} tick={AXIS} interval={0} />
                <Tooltip content={<ChartTooltip formatValue={(v: number) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                <Bar dataKey="Gross" fill="var(--chart-1)" radius={[0, 4, 4, 0]} maxBarSize={16} />
                <Bar dataKey="Net" fill="var(--chart-2)" radius={[0, 4, 4, 0]} maxBarSize={16} />
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>

          <Card>
            <CardHeader title="Runs in period" />
            <CardBody padding="none">
              {loading ? (
                <div style={{ padding: 16 }}><SkeletonCard /></div>
              ) : (d?.runs ?? []).length === 0 ? (
                <EmptyState size="sm" title="No finalized or paid runs" description="Runs appear here once they are finalized." />
              ) : (
                <div className={crud.tableScroll}>
                  <table className={crud.lines} style={{ minWidth: 820 }}>
                    <thead><tr><th>Run</th><th>Period</th><th>Status</th><th className={crud.r}>Employees</th><th className={crud.r}>Gross</th><th className={crud.r}>Tax</th><th className={crud.r}>Deductions</th><th className={crud.r}>Employer</th><th className={crud.r}>Net</th></tr></thead>
                    <tbody>
                      {d!.runs.map((r) => (
                        <tr key={r.id}>
                          <td><Link className={styles.linkBtn} href={`/payroll/runs/${r.id}`}>{r.number}</Link><div className={styles.hint}>{r.name}</div></td>
                          <td>{periodLabel(r.period_start, r.period_end)}</td>
                          <td><RunStatusBadge status={r.status} size="sm" /></td>
                          <td className={crud.r}>{r.employee_count}</td>
                          <td className={crud.r}>{formatMoney(r.gross, r.currency)}</td>
                          <td className={crud.r}>{formatMoney(r.tax, r.currency)}</td>
                          <td className={crud.r}>{formatMoney(r.deductions, r.currency)}</td>
                          <td className={crud.r}>{formatMoney(r.employer_contributions, r.currency)}</td>
                          <td className={crud.r}>{formatMoney(r.net, r.currency)}</td>
                        </tr>
                      ))}
                    </tbody>
                    <tfoot>
                      <tr>
                        <td colSpan={3}>Total</td>
                        <td className={crud.r}>{d!.totals.headcount_paid}</td>
                        <td className={crud.r}>{money(d!.totals.gross)}</td>
                        <td className={crud.r}>{money(d!.totals.tax)}</td>
                        <td className={crud.r}>{money(d!.totals.deductions)}</td>
                        <td className={crud.r}>{money(d!.totals.employer_contributions)}</td>
                        <td className={crud.r}>{money(d!.totals.net)}</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              )}
            </CardBody>
          </Card>
        </>
      )}
    </div>
  );
}

const KIND_ORDER: ComponentKind[] = ['earning', 'deduction', 'employer_contribution'];

function ComponentsReport() {
  const companyId = useCompanyId();
  const runs = payrollRuns.useList({ per_page: 50, sort: '-period_end' });
  const options = (runs.data?.data ?? [])
    .filter((r) => r.status !== 'cancelled' && r.status !== 'draft')
    .map((r) => ({ value: String(r.id), label: `${r.number} · ${r.name}` }));
  const [picked, setPicked] = useState('');
  const runId = picked || options[0]?.value || '';
  const q = useQuery({
    queryKey: [companyId, 'payroll-reports', 'components', runId],
    queryFn: () => payrollApi.components(Number(runId)),
    enabled: !!runId,
  });
  const d = q.data;

  return (
    <Card>
      <CardHeader title="Pay components by run" description="Totals of every earning, deduction and employer contribution line in one run." />
      <CardBody>
        <div className={styles.filters} style={{ marginBottom: 16 }}>
          <FormField label="Run">
            <Select value={runId} onChange={(e) => setPicked(e.target.value)} options={options} placeholder={runs.isLoading ? 'Loading…' : 'No calculated runs'} style={{ minWidth: 280 }} />
          </FormField>
          {d && <RunStatusBadge status={d.run.status} />}
        </div>
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} />
        ) : !runId ? (
          <EmptyState size="sm" title="No calculated runs yet" />
        ) : !d ? (
          <SkeletonCard />
        ) : d.components.length === 0 ? (
          <EmptyState size="sm" title="This run has no payslip lines" description="Calculate the run to generate payslips." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 560 }}>
              <thead><tr><th>Component</th><th>Type</th><th className={crud.r}>Payslips</th><th className={crud.r}>Total</th></tr></thead>
              {KIND_ORDER.map((kind) => {
                const rows = d.components.filter((c) => c.kind === kind);
                if (rows.length === 0) return null;
                return (
                  <tbody key={kind}>
                    {rows.map((c) => (
                      <tr key={`${c.kind}-${c.code}`}>
                        <td>{c.name} <span className={styles.hint}>({c.code})</span></td>
                        <td>{kindLabel(c.kind)}</td>
                        <td className={crud.r}>{c.payslips}</td>
                        <td className={crud.r}>{formatMoney(c.total, d.run.currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                );
              })}
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

export function PayrollReports() {
  return (
    <div className={styles.stack}>
      <SummaryReport />
      <ComponentsReport />
      <p className={styles.hint}>Figures are as of {formatDate(todayIso())}. Payroll data is fetched on demand and not stored on this device.</p>
    </div>
  );
}
