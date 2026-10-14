'use client';

import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { FileText, Percent, ShoppingCart, Target } from 'lucide-react';
import { DateInput, EmptyState, ErrorState, FormField, MetricCard, PageHeader, SegmentedControl } from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { useCan } from '@/lib/auth/useCan';
import { formatCompact, formatDate, formatMoney, formatPercent } from '@/lib/format';
import { useAppSelector } from '@/store';
import { salesApi, type PerformanceGroup, type PerformanceRow } from '@/features/sales/api';
import styles from '@/features/sales/sales.module.scss';

const GROUPS: { value: PerformanceGroup; label: string }[] = [
  { value: 'rep', label: 'By rep' },
  { value: 'customer', label: 'By customer' },
  { value: 'month', label: 'By month' },
];

function rowLabel(r: PerformanceRow, group: PerformanceGroup): string {
  if (group === 'rep') return r.rep?.name ?? 'Unassigned';
  if (group === 'customer') return r.customer?.name ?? 'Unknown customer';
  const m = typeof r.key === 'string' ? r.key : r.month ?? '';
  return m ? format(parseISO(`${m}-01`), 'MMM yyyy') : '—';
}

export default function SalesReportsPage() {
  const can = useCan();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const allowed = can('reports.view');
  const [group, setGroup] = useState<PerformanceGroup>('rep');
  const [from, setFrom] = useState<string | null>(null);
  const [to, setTo] = useState<string | null>(null);

  const params = { group_by: group, ...(from ? { from } : {}), ...(to ? { to } : {}) };
  const perf = useQuery({ queryKey: [companyId, 'sales-reports', 'performance', params], queryFn: () => salesApi.performance(params), enabled: allowed });
  const pipe = useQuery({ queryKey: [companyId, 'sales-reports', 'pipeline'], queryFn: salesApi.pipelineReport, enabled: allowed });

  const money = (v: string | number | null | undefined) => formatMoney(v, currency);
  const d = perf.data;
  const rows = useMemo(
    () => (d?.rows ?? []).map((r) => ({ name: rowLabel(r, d!.group_by), Quotations: Number(r.quotations.total), Orders: Number(r.orders.total), raw: r })),
    [d],
  );
  const stages = useMemo(
    () => (pipe.data?.stages ?? []).map((s) => ({ name: s.stage.name, Weighted: Number(s.weighted_value), Total: Number(s.total_value), raw: s })),
    [pipe.data],
  );

  if (!allowed) return <EmptyState title="Sales reports unavailable" description="Your role does not include the “View reports” permission." />;

  const t = d?.totals;
  const rangeLabel = d ? `${formatDate(d.from)} – ${formatDate(d.to)}` : '';

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Sales reports"
        description={rangeLabel ? `Performance for ${rangeLabel}. Orders count once approved.` : 'Quotation and order performance with pipeline health.'}
      />

      <div className={styles.inlineFilters}>
        <SegmentedControl<PerformanceGroup> aria-label="Group by" value={group} onChange={setGroup} options={GROUPS} size="sm" />
        <FormField label="From">
          <DateInput value={from} max={to ?? undefined} onValueChange={setFrom} />
        </FormField>
        <FormField label="To">
          <DateInput value={to} min={from ?? undefined} onValueChange={setTo} />
        </FormField>
      </div>

      {perf.error ? (
        <ErrorState error={perf.error} onRetry={() => void perf.refetch()} />
      ) : (
        <>
          <section className={styles.metrics} aria-label="Totals">
            <MetricCard label="Quotations issued" icon={<FileText size={18} />} loading={perf.isLoading} value={money(t?.quotations.total)} footer={t ? `${t.quotations.count} quotations` : undefined} />
            <MetricCard label="Orders won" icon={<ShoppingCart size={18} />} loading={perf.isLoading} value={money(t?.orders.total)} footer={t ? `${t.orders.count} approved or delivered` : undefined} />
            <MetricCard
              label="Conversion rate"
              icon={<Percent size={18} />}
              loading={perf.isLoading}
              value={t?.conversion_rate ? formatPercent(t.conversion_rate) : '—'}
              unavailable={!perf.isLoading && !t?.conversion_rate}
              unavailableText="No quotations in range"
              footer={t ? `${t.quotations.converted} of ${t.quotations.count} quotations converted` : undefined}
            />
          </section>

          <ChartCard
            title="Quotations vs orders"
            description={GROUPS.find((g) => g.value === group)?.label}
            loading={perf.isLoading}
            empty={!perf.isLoading && rows.length === 0}
            emptyText="No quotations or orders in this range"
            height={320}
            legend={[
              { label: 'Quotations', color: 'var(--chart-1)' },
              { label: 'Orders', color: 'var(--chart-2)' },
            ]}
            table={
              <table>
                <thead>
                  <tr>
                    <th>{group === 'rep' ? 'Sales rep' : group === 'customer' ? 'Customer' : 'Month'}</th>
                    <th className="num">Quotations</th>
                    <th className="num">Quoted</th>
                    <th className="num">Orders</th>
                    <th className="num">Ordered</th>
                    <th className="num">Conversion</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((r) => (
                    <tr key={`${r.raw.key ?? 'none'}`}>
                      <td>{r.name}</td>
                      <td className="num">{r.raw.quotations.count}</td>
                      <td className="num">{money(r.raw.quotations.total)}</td>
                      <td className="num">{r.raw.orders.count}</td>
                      <td className="num">{money(r.raw.orders.total)}</td>
                      <td className="num">{r.raw.conversion_rate ? formatPercent(r.raw.conversion_rate) : '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            }
          >
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={rows} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barGap={2}>
                <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
                <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} interval="preserveStartEnd" minTickGap={8} />
                <YAxis tickLine={false} axisLine={false} width={56} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
                <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                <Bar dataKey="Quotations" fill="var(--chart-1)" radius={[4, 4, 0, 0]} maxBarSize={28} />
                <Bar dataKey="Orders" fill="var(--chart-2)" radius={[4, 4, 0, 0]} maxBarSize={28} />
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>
        </>
      )}

      {pipe.error ? (
        <ErrorState error={pipe.error} onRetry={() => void pipe.refetch()} />
      ) : (
        <>
          <section className={styles.metrics} aria-label="Pipeline totals">
            <MetricCard label="Open pipeline" icon={<Target size={18} />} loading={pipe.isLoading} value={money(pipe.data?.open.total_value)} footer={pipe.data ? `${pipe.data.open.count} open deals` : undefined} />
            <MetricCard label="Weighted pipeline" icon={<Target size={18} />} loading={pipe.isLoading} value={money(pipe.data?.open.weighted_value)} footer="Σ value × stage probability" />
            <MetricCard
              label="Win rate (90 days)"
              icon={<Percent size={18} />}
              loading={pipe.isLoading}
              value={pipe.data?.win_rate_90d.rate ? formatPercent(pipe.data.win_rate_90d.rate) : '—'}
              unavailable={!pipe.isLoading && !pipe.data?.win_rate_90d.rate}
              unavailableText="No closed deals yet"
              footer={pipe.data ? `${pipe.data.win_rate_90d.won} won · ${pipe.data.win_rate_90d.lost} lost` : undefined}
            />
          </section>

          <ChartCard
            title="Weighted value by stage"
            description="All opportunities, value × probability"
            loading={pipe.isLoading}
            empty={!pipe.isLoading && stages.every((s) => s.Total === 0)}
            emptyText="No opportunities in the pipeline"
            height={Math.max(200, stages.length * 44)}
            legend={[
              { label: 'Weighted', color: 'var(--chart-1)' },
              { label: 'Total', color: 'var(--chart-4)' },
            ]}
            table={
              <table>
                <thead>
                  <tr><th>Stage</th><th className="num">Probability</th><th className="num">Deals</th><th className="num">Total</th><th className="num">Weighted</th></tr>
                </thead>
                <tbody>
                  {stages.map((s) => (
                    <tr key={s.raw.stage.id}>
                      <td>{s.name}</td>
                      <td className="num">{s.raw.stage.probability}%</td>
                      <td className="num">{s.raw.count}</td>
                      <td className="num">{money(s.raw.total_value)}</td>
                      <td className="num">{money(s.raw.weighted_value)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            }
          >
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={stages} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }} barGap={2}>
                <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
                <XAxis type="number" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
                <YAxis type="category" dataKey="name" tickLine={false} axisLine={false} width={110} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} />
                <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                <Bar dataKey="Weighted" fill="var(--chart-1)" radius={[0, 4, 4, 0]} maxBarSize={16} />
                <Bar dataKey="Total" fill="var(--chart-4)" radius={[0, 4, 4, 0]} maxBarSize={16} />
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>
        </>
      )}
    </div>
  );
}
