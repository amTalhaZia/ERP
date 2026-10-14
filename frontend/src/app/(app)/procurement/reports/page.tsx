'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { format, startOfYear } from 'date-fns';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ArrowDown, ArrowUp, ArrowUpDown, CheckCircle2, ClipboardList, Hourglass, Wallet } from 'lucide-react';
import {
  Alert, Card, CardBody, CardHeader, DateInput, EmptyState, ErrorState, MetricCard, PageHeader, SegmentedControl, SkeletonTable, Tab, TabList, TabPanel, Tabs,
} from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { useCan } from '@/lib/auth/useCan';
import { formatCompact, formatMoney, formatPercent, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { procurementReports, type SpendGroupBy, type StatusRow, type SupplierPerformanceRow } from '@/features/procurement/api';
import { DocStatus, Stars } from '@/features/procurement/components';
import { formatQty, fromUnits, toUnits } from '@/features/procurement/decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/procurement/procurement.module.scss';

const GROUPS: { value: SpendGroupBy; label: string }[] = [
  { value: 'supplier', label: 'Supplier' },
  { value: 'month', label: 'Month' },
  { value: 'department', label: 'Department' },
];

function useCompanyKey() {
  return useAppSelector((s) => s.session.current_company?.id ?? 0);
}

function SpendTab({ from, to, fallbackCurrency }: { from: string; to: string; fallbackCurrency: string }) {
  const companyId = useCompanyKey();
  const [groupBy, setGroupBy] = useState<SpendGroupBy>('supplier');
  const q = useQuery({
    queryKey: [companyId, 'procurement-reports', 'spend', groupBy, from, to],
    queryFn: () => procurementReports.spend({ from: from || undefined, to: to || undefined, group_by: groupBy }),
  });
  const currency = q.data?.meta.currency ?? fallbackCurrency;
  const rows = q.data?.data.rows ?? [];
  const money = (v: number | string) => formatMoney(v, currency);
  const horizontal = groupBy !== 'month';
  // Numbers are for drawing only; tables and totals render the decimal strings.
  const chartData = rows.map((r) => ({ label: groupBy === 'month' ? format(new Date(`${r.label}-01T00:00:00`), 'MMM yyyy') : r.label, Spend: Number(r.total) }));
  const height = horizontal ? Math.max(240, Math.min(rows.length, 15) * 32 + 40) : 300;

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;

  return (
    <div className={styles.stack}>
      <div className={styles.between}>
        <SegmentedControl aria-label="Group spend by" options={GROUPS} value={groupBy} onChange={setGroupBy} size="sm" />
        {q.data && (
          <span className={crud.muted}>
            {q.data.data.order_count} orders · <strong style={{ color: 'var(--color-text)' }}>{money(q.data.data.total)}</strong> committed
          </span>
        )}
      </div>
      <ChartCard
        title={`Committed spend by ${groupBy}`}
        description="Approved, received and closed purchase orders"
        loading={q.isLoading}
        empty={!q.isLoading && rows.length === 0}
        emptyText="No committed spend in this period"
        height={height}
        table={
          <table className={crud.lines}>
            <thead><tr><th>{humanize(groupBy)}</th><th className={crud.r}>Orders</th><th className={crud.r}>Spend</th><th className={crud.r}>Share</th></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={String(r.key ?? r.label)}>
                  <td>{r.label}</td>
                  <td className={crud.r}>{r.order_count}</td>
                  <td className={crud.r}>{money(r.total)}</td>
                  <td className={crud.r}>{q.data && toUnits(q.data.data.total) > 0n ? formatPercent(Number((toUnits(r.total) * 10000n) / toUnits(q.data.data.total)) / 100) : '—'}</td>
                </tr>
              ))}
            </tbody>
            <tfoot><tr><td>Total</td><td className={crud.r}>{q.data?.data.order_count}</td><td className={crud.r}>{money(q.data?.data.total ?? '0')}</td><td /></tr></tfoot>
          </table>
        }
      >
        <ResponsiveContainer width="100%" height="100%">
          {horizontal ? (
            <BarChart data={chartData.slice(0, 15)} layout="vertical" margin={{ top: 4, right: 16, left: 8, bottom: 0 }}>
              <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
              <XAxis type="number" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
              <YAxis type="category" dataKey="label" width={140} tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} interval={0} />
              <Tooltip content={<ChartTooltip formatValue={money} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
              <Bar dataKey="Spend" fill="var(--chart-1)" radius={[0, 4, 4, 0]} maxBarSize={22} />
            </BarChart>
          ) : (
            <BarChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
              <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
              <XAxis dataKey="label" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} minTickGap={12} />
              <YAxis tickLine={false} axisLine={false} width={56} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
              <Tooltip content={<ChartTooltip formatValue={money} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
              <Bar dataKey="Spend" fill="var(--chart-1)" radius={[4, 4, 0, 0]} maxBarSize={36} />
            </BarChart>
          )}
        </ResponsiveContainer>
      </ChartCard>
      {horizontal && rows.length > 15 && <Alert variant="info">The chart shows the top 15; switch to the table view for all {rows.length} rows.</Alert>}
    </div>
  );
}

function StatusTable({ title, rows, currency, totalLabel }: { title: string; rows: StatusRow[]; currency: string; totalLabel: string }) {
  const count = rows.reduce((a, r) => a + r.count, 0);
  const total = rows.reduce((a, r) => a + toUnits(r.total), 0n);
  return (
    <Card>
      <CardHeader title={title} />
      <CardBody padding="none">
        <div className={crud.tableScroll}>
          <table className={crud.lines}>
            <thead><tr><th>Status</th><th className={crud.r}>Count</th><th className={crud.r}>{totalLabel}</th></tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.status}><td><DocStatus status={r.status} /></td><td className={crud.r}>{r.count}</td><td className={crud.r}>{formatMoney(r.total, currency)}</td></tr>
              ))}
            </tbody>
            <tfoot><tr><td>Total</td><td className={crud.r}>{count}</td><td className={crud.r}>{formatMoney(fromUnits(total), currency)}</td></tr></tfoot>
          </table>
        </div>
      </CardBody>
    </Card>
  );
}

function StatusTab({ from, to, fallbackCurrency }: { from: string; to: string; fallbackCurrency: string }) {
  const companyId = useCompanyKey();
  const q = useQuery({
    queryKey: [companyId, 'procurement-reports', 'status', from, to],
    queryFn: () => procurementReports.statusSummary({ from: from || undefined, to: to || undefined }),
  });
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const d = q.data?.data;
  const currency = q.data?.meta.currency ?? fallbackCurrency;
  const committed = (d?.purchase_orders ?? []).filter((r) => ['approved', 'partially_received', 'received', 'closed'].includes(r.status));
  const committedTotal = committed.reduce((a, r) => a + toUnits(r.total), 0n);
  const pendingReq = d?.requisitions.find((r) => r.status === 'submitted')?.count ?? 0;
  const pendingPo = d?.purchase_orders.find((r) => r.status === 'submitted')?.count ?? 0;

  return (
    <div className={styles.stack}>
      <div className={styles.metrics}>
        <MetricCard label="Committed spend" icon={<Wallet size={18} />} loading={q.isLoading} value={formatMoney(fromUnits(committedTotal), currency)} footer={`${committed.reduce((a, r) => a + r.count, 0)} purchase orders`} />
        <MetricCard label="Awaiting approval" icon={<ClipboardList size={18} />} loading={q.isLoading} value={pendingReq + pendingPo} footer={`${pendingReq} requisitions · ${pendingPo} purchase orders`} />
        <MetricCard
          label="Avg. requisition approval"
          icon={<Hourglass size={18} />}
          loading={q.isLoading}
          unavailable={!!d && d.avg_requisition_approval_hours === null}
          unavailableText="No approvals yet"
          value={d?.avg_requisition_approval_hours != null ? `${d.avg_requisition_approval_hours} h` : '—'}
          footer="Submitted → approved"
        />
        <MetricCard
          label="On-time receipts"
          icon={<CheckCircle2 size={18} />}
          loading={q.isLoading}
          unavailable={!!d && d.on_time_receipt.rate === null}
          unavailableText="No dated receipts"
          value={d?.on_time_receipt.rate != null ? formatPercent(d.on_time_receipt.rate, { ratio: true }) : '—'}
          footer={d ? `${d.on_time_receipt.on_time} of ${d.on_time_receipt.receipts} by the expected date` : undefined}
        />
      </div>
      {q.isLoading ? (
        <SkeletonTable rows={6} cols={3} />
      ) : d ? (
        <div className={crud.detailGrid}>
          <StatusTable title="Purchase orders" rows={d.purchase_orders} currency={currency} totalLabel="Value" />
          <StatusTable title="Requisitions" rows={d.requisitions} currency={currency} totalLabel="Estimated" />
        </div>
      ) : null}
    </div>
  );
}

type SortKey = 'name' | 'order_count' | 'total' | 'receipt_count' | 'avg_lead_time_days' | 'on_time_rate' | 'rejected_ratio' | 'rating';

function PerformanceTab({ from, to, fallbackCurrency }: { from: string; to: string; fallbackCurrency: string }) {
  const companyId = useCompanyKey();
  const q = useQuery({
    queryKey: [companyId, 'procurement-reports', 'performance', from, to],
    queryFn: () => procurementReports.supplierPerformance({ from: from || undefined, to: to || undefined }),
  });
  const [sort, setSort] = useState<{ key: SortKey; dir: 1 | -1 }>({ key: 'total', dir: -1 });
  const currency = q.data?.meta.currency ?? fallbackCurrency;

  const rows = useMemo(() => {
    const data = [...(q.data?.data ?? [])];
    const val = (r: SupplierPerformanceRow): number | string | bigint | null => {
      if (sort.key === 'name') return (r.name ?? '').toLowerCase();
      if (sort.key === 'total') return toUnits(r.total);
      return r[sort.key];
    };
    return data.sort((a, b) => {
      const x = val(a);
      const y = val(b);
      if (x === null && y === null) return 0;
      if (x === null) return 1; // nulls last regardless of direction
      if (y === null) return -1;
      return (x < y ? -1 : x > y ? 1 : 0) * sort.dir;
    });
  }, [q.data, sort]);

  const th = (key: SortKey, label: string, right = true) => {
    const active = sort.key === key;
    const Icon = !active ? ArrowUpDown : sort.dir === 1 ? ArrowUp : ArrowDown;
    return (
      <th className={right ? crud.r : undefined} aria-sort={active ? (sort.dir === 1 ? 'ascending' : 'descending') : 'none'}>
        <button type="button" className={styles.sortBtn} onClick={() => setSort((s) => ({ key, dir: s.key === key ? (s.dir === 1 ? -1 : 1) : key === 'name' ? 1 : -1 }))}>
          {label} <Icon size={12} aria-hidden />
        </button>
      </th>
    );
  };

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  return (
    <Card>
      <CardHeader title="Supplier performance" description="Committed spend, delivery lead time vs promise, on-time rate and rejection ratio." />
      <CardBody padding="none">
        {q.isLoading ? (
          <div style={{ padding: 16 }}><SkeletonTable rows={5} cols={8} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" title="No supplier activity in this period" />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 980 }}>
              <thead>
                <tr>
                  {th('name', 'Supplier', false)}
                  {th('rating', 'Rating', false)}
                  {th('order_count', 'Orders')}
                  {th('total', 'Spend')}
                  {th('receipt_count', 'Receipts')}
                  {th('avg_lead_time_days', 'Lead time')}
                  {th('on_time_rate', 'On time')}
                  {th('rejected_ratio', 'Rejected')}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => {
                  const slow = r.avg_lead_time_days != null && r.promised_lead_time_days != null && r.avg_lead_time_days > r.promised_lead_time_days;
                  return (
                    <tr key={r.supplier_id}>
                      <td>
                        <div className={crud.cellMain}>
                          <strong><Link href={`/procurement/suppliers/${r.supplier_id}`}>{r.name ?? `#${r.supplier_id}`}</Link></strong>
                          <span>{r.code ?? ''}</span>
                        </div>
                      </td>
                      <td><Stars rating={r.rating} /></td>
                      <td className={crud.r}>{r.order_count}</td>
                      <td className={crud.r}>{formatMoney(r.total, currency)}</td>
                      <td className={crud.r}>{r.receipt_count}</td>
                      <td className={crud.r}>
                        <span style={slow ? { color: 'var(--color-danger-text)' } : undefined}>{r.avg_lead_time_days != null ? `${r.avg_lead_time_days} d` : '—'}</span>
                        {r.promised_lead_time_days != null && <span className={styles.linked}>promised {r.promised_lead_time_days} d</span>}
                      </td>
                      <td className={crud.r}>{r.on_time_rate != null ? formatPercent(r.on_time_rate, { ratio: true }) : '—'}</td>
                      <td className={crud.r}>
                        {r.rejected_ratio != null ? formatPercent(r.rejected_ratio, { ratio: true }) : '—'}
                        {toUnits(r.quantity_rejected) > 0n && <span className={styles.linked}>{formatQty(r.quantity_rejected)} of {formatQty(fromUnits(toUnits(r.quantity_received) + toUnits(r.quantity_rejected)))}</span>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

export default function ProcurementReportsPage() {
  const can = useCan();
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [from, setFrom] = useState(() => format(startOfYear(new Date()), 'yyyy-MM-dd'));
  const [to, setTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));
  const [tab, setTab] = useState('spend');

  if (!can('reports.view')) return <Alert variant="warning">You don’t have permission to view reports.</Alert>;
  const invalidRange = !!from && !!to && to < from;

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Procurement reports"
        description="Spend, pipeline status and supplier performance."
        actions={
          <div className={styles.row}>
            <DateInput aria-label="From" value={from} max={to || undefined} onValueChange={(v) => setFrom(v ?? '')} />
            <DateInput aria-label="To" value={to} min={from || undefined} onValueChange={(v) => setTo(v ?? '')} />
          </div>
        }
      />
      {invalidRange ? (
        <Alert variant="warning">The end date must be on or after the start date.</Alert>
      ) : (
        <Tabs value={tab} onValueChange={setTab}>
          <TabList>
            <Tab value="spend">Spend</Tab>
            <Tab value="status">Status summary</Tab>
            <Tab value="performance">Supplier performance</Tab>
          </TabList>
          <TabPanel value="spend"><div style={{ paddingTop: 16 }}><SpendTab from={from} to={to} fallbackCurrency={currency} /></div></TabPanel>
          <TabPanel value="status"><div style={{ paddingTop: 16 }}><StatusTab from={from} to={to} fallbackCurrency={currency} /></div></TabPanel>
          <TabPanel value="performance"><div style={{ paddingTop: 16 }}><PerformanceTab from={from} to={to} fallbackCurrency={currency} /></div></TabPanel>
        </Tabs>
      )}
    </div>
  );
}
