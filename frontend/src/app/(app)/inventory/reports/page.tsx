'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { format, parseISO, subDays } from 'date-fns';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Boxes, Layers, Wallet } from 'lucide-react';
import {
  Card, CardBody, CardHeader, DateInput, ErrorState, MetricCard, PageHeader, SegmentedControl, Select, Skeleton, Switch, Tab, TabList, TabPanel, Tabs,
} from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { formatCompact, formatDateTime, formatMoney } from '@/lib/format';
import { inventoryReports } from '@/features/inventory/api';
import { formatQty } from '@/features/inventory/decimal';
import { useCompanyId, useCurrency, useWarehouseOptions } from '@/features/inventory/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

const axisTick = { fill: 'var(--chart-axis)', fontSize: 12 };

function Valuation() {
  const companyId = useCompanyId();
  const fallbackCurrency = useCurrency();
  const wh = useWarehouseOptions();
  const [warehouseId, setWarehouseId] = useState('');
  const [byCategory, setByCategory] = useState(false);
  const params: Record<string, unknown> = { ...(warehouseId ? { warehouse_id: warehouseId } : {}), ...(byCategory ? { group_by: 'category' } : {}) };
  const q = useQuery({ queryKey: [companyId, 'inventory-reports', 'valuation', params], queryFn: () => inventoryReports.valuation(params) });
  const d = q.data;
  const currency = d?.currency ?? fallbackCurrency;
  const money = (v: string | number) => formatMoney(v, currency);
  // Recharts needs numbers; conversion happens only at the display edge.
  const chartData = (d?.warehouses ?? []).map((w) => ({ name: w.warehouse_code, label: w.warehouse_name, Value: Number(w.stock_value) }));

  return (
    <div className={styles.stack}>
      <div className={styles.row}>
        <div className={styles.actions}>
          <Select aria-label="Warehouse" placeholder="All warehouses" value={warehouseId} options={wh.options} onChange={(e) => setWarehouseId(e.target.value)} />
          <Switch checked={byCategory} onCheckedChange={setByCategory} label="Break down by category" />
        </div>
        {d && <span className={crud.muted}>As of {formatDateTime(d.as_of)}</span>}
      </div>

      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : (
        <>
          <div className={styles.metrics}>
            <MetricCard label="Stock value" icon={<Wallet size={18} />} loading={q.isLoading} value={d ? money(d.total.stock_value) : '—'} />
            <MetricCard label="Products in stock" icon={<Boxes size={18} />} loading={q.isLoading} value={d ? d.total.item_count.toLocaleString() : '—'} />
            <MetricCard label="Units on hand" icon={<Layers size={18} />} loading={q.isLoading} value={d ? formatQty(d.total.quantity_on_hand) : '—'} />
          </div>

          <ChartCard
            title="Value by warehouse"
            description="Quantity on hand × moving average cost"
            loading={q.isLoading}
            empty={!q.isLoading && chartData.length === 0}
            emptyText="No stock on hand"
            height={Math.max(160, chartData.length * 44)}
            table={
              <table>
                <thead><tr><th>Warehouse</th><th className="num">Value</th></tr></thead>
                <tbody>{(d?.warehouses ?? []).map((w) => <tr key={w.warehouse_id}><td>{w.warehouse_code} · {w.warehouse_name}</td><td className="num">{money(w.stock_value)}</td></tr>)}</tbody>
              </table>
            }
          >
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData} layout="vertical" margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                <CartesianGrid horizontal={false} stroke="var(--chart-grid)" />
                <XAxis type="number" tickLine={false} axisLine={false} tick={axisTick} tickFormatter={(v: number) => formatCompact(v)} />
                <YAxis type="category" dataKey="name" tickLine={false} axisLine={false} width={72} tick={axisTick} />
                <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                <Bar dataKey="Value" fill="var(--chart-1)" radius={[0, 4, 4, 0]} maxBarSize={24} />
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>

          <Card>
            <CardHeader title="By warehouse" />
            <CardBody padding="none">
              {q.isLoading ? (
                <div style={{ padding: 16 }}><Skeleton variant="text" lines={4} /></div>
              ) : (
                <div className={crud.tableScroll}>
                  <table className={crud.lines} style={{ minWidth: 520 }}>
                    <thead><tr><th>Warehouse</th><th className={crud.r}>Products</th><th className={crud.r}>Units</th><th className={crud.r}>Value</th></tr></thead>
                    <tbody>
                      {(d?.warehouses ?? []).length === 0 && <tr><td colSpan={4} className={crud.muted}>No stock on hand.</td></tr>}
                      {(d?.warehouses ?? []).map((w) => (
                        <tr key={w.warehouse_id}>
                          <td><span className={crud.mono}>{w.warehouse_code}</span> {w.warehouse_name}</td>
                          <td className={crud.r}>{w.item_count}</td>
                          <td className={crud.r}>{formatQty(w.quantity_on_hand)}</td>
                          <td className={crud.r}>{money(w.stock_value)}</td>
                        </tr>
                      ))}
                    </tbody>
                    {d && (
                      <tfoot>
                        <tr><td>Total</td><td className={crud.r}>{d.total.item_count}</td><td className={crud.r}>{formatQty(d.total.quantity_on_hand)}</td><td className={crud.r}>{money(d.total.stock_value)}</td></tr>
                      </tfoot>
                    )}
                  </table>
                </div>
              )}
            </CardBody>
          </Card>

          {byCategory && d?.categories && (
            <Card>
              <CardHeader title="By category" />
              <CardBody padding="none">
                <div className={crud.tableScroll}>
                  <table className={crud.lines} style={{ minWidth: 520 }}>
                    <thead><tr><th>Category</th><th className={crud.r}>Products</th><th className={crud.r}>Units</th><th className={crud.r}>Value</th></tr></thead>
                    <tbody>
                      {d.categories.length === 0 && <tr><td colSpan={4} className={crud.muted}>No stock on hand.</td></tr>}
                      {d.categories.map((c) => (
                        <tr key={c.category_id ?? 'none'}>
                          <td>{c.category_name}</td>
                          <td className={crud.r}>{c.item_count}</td>
                          <td className={crud.r}>{formatQty(c.quantity_on_hand)}</td>
                          <td className={crud.r}>{money(c.stock_value)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </CardBody>
            </Card>
          )}
        </>
      )}
    </div>
  );
}

function MovementSummary() {
  const companyId = useCompanyId();
  const fallbackCurrency = useCurrency();
  const wh = useWarehouseOptions();
  const [group, setGroup] = useState<'day' | 'month'>('day');
  const [from, setFrom] = useState(() => format(subDays(new Date(), 29), 'yyyy-MM-dd'));
  const [to, setTo] = useState(() => format(new Date(), 'yyyy-MM-dd'));
  const [warehouseId, setWarehouseId] = useState('');
  const params: Record<string, unknown> = { group, from: from || undefined, to: to || undefined, ...(warehouseId ? { warehouse_id: warehouseId } : {}) };
  const q = useQuery({ queryKey: [companyId, 'inventory-reports', 'movement-summary', params], queryFn: () => inventoryReports.movementSummary(params) });
  const d = q.data;
  const currency = d?.currency ?? fallbackCurrency;
  const label = (p: string) => (group === 'month' ? format(parseISO(`${p}-01`), 'MMM yyyy') : format(parseISO(p), 'dd MMM'));
  const chartData = (d?.rows ?? []).map((r) => ({ period: label(r.period), In: Number(r.quantity_in), Out: Number(r.quantity_out) }));

  return (
    <div className={styles.stack}>
      <div className={styles.actions}>
        <SegmentedControl aria-label="Group by" value={group} onChange={setGroup} options={[{ value: 'day', label: 'Daily' }, { value: 'month', label: 'Monthly' }]} />
        <DateInput aria-label="From" value={from} max={to || undefined} onValueChange={(v) => setFrom(v ?? '')} />
        <DateInput aria-label="To" value={to} min={from || undefined} onValueChange={(v) => setTo(v ?? '')} />
        <Select aria-label="Warehouse" placeholder="All warehouses" value={warehouseId} options={wh.options} onChange={(e) => setWarehouseId(e.target.value)} />
      </div>

      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : (
        <>
          <div className={styles.metrics}>
            <MetricCard label="Units in" loading={q.isLoading} value={d ? formatQty(d.totals.quantity_in) : '—'} footer={d ? formatMoney(d.totals.value_in, currency) : undefined} />
            <MetricCard label="Units out" loading={q.isLoading} value={d ? formatQty(d.totals.quantity_out) : '—'} footer={d ? formatMoney(d.totals.value_out, currency) : undefined} />
            <MetricCard label="Movements" loading={q.isLoading} value={d ? d.totals.movements.toLocaleString() : '—'} />
          </div>

          <ChartCard
            title="Quantity in vs out"
            description={group === 'month' ? 'Per month' : 'Per day'}
            loading={q.isLoading}
            empty={!q.isLoading && chartData.length === 0}
            legend={[
              { label: 'In', color: 'var(--chart-2)' },
              { label: 'Out', color: 'var(--chart-3)' },
            ]}
            table={
              <table>
                <thead><tr><th>Period</th><th className="num">In</th><th className="num">Out</th><th className="num">Value in</th><th className="num">Value out</th></tr></thead>
                <tbody>
                  {(d?.rows ?? []).map((r) => (
                    <tr key={r.period}><td>{label(r.period)}</td><td className="num">{formatQty(r.quantity_in)}</td><td className="num">{formatQty(r.quantity_out)}</td><td className="num">{formatMoney(r.value_in, currency)}</td><td className="num">{formatMoney(r.value_out, currency)}</td></tr>
                  ))}
                </tbody>
              </table>
            }
          >
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={chartData} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barGap={2}>
                <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
                <XAxis dataKey="period" tickLine={false} axisLine={false} tick={axisTick} minTickGap={16} />
                <YAxis tickLine={false} axisLine={false} width={56} tick={axisTick} tickFormatter={(v: number) => formatCompact(v)} />
                <Tooltip content={<ChartTooltip formatValue={(v) => formatQty(String(v))} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                <Bar dataKey="In" fill="var(--chart-2)" radius={[4, 4, 0, 0]} maxBarSize={24} />
                <Bar dataKey="Out" fill="var(--chart-3)" radius={[4, 4, 0, 0]} maxBarSize={24} />
              </BarChart>
            </ResponsiveContainer>
          </ChartCard>
        </>
      )}
    </div>
  );
}

export default function InventoryReportsPage() {
  const [tab, setTab] = useState('valuation');
  return (
    <div className={styles.stack}>
      <PageHeader title="Inventory reports" description="Stock valuation and movement trends." />
      <Tabs value={tab} onValueChange={setTab}>
        <TabList>
          <Tab value="valuation">Valuation</Tab>
          <Tab value="movements">Movement summary</Tab>
        </TabList>
        <TabPanel value="valuation" style={{ paddingTop: 16 }}><Valuation /></TabPanel>
        <TabPanel value="movements" style={{ paddingTop: 16 }}><MovementSummary /></TabPanel>
      </Tabs>
    </div>
  );
}
