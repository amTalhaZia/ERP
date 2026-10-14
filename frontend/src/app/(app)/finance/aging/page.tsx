'use client';

import { Suspense, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { format, startOfYear } from 'date-fns';
import { Printer } from 'lucide-react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import {
  Alert, Button, Card, CardBody, CardHeader, DateInput, EmptyState, ErrorState, FormField, PageHeader, Select, Skeleton, Tab, TabList, TabPanel, Tabs,
} from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { useCan } from '@/lib/auth/useCan';
import { formatCompact, formatDate, formatMoney } from '@/lib/format';
import {
  AGING_BUCKETS, partnerLabel, receivablesReports, useCompanyId, useCurrency, usePartners, type AgingReport,
} from '@/features/finance/receivables/api';
import styles from '@/features/finance/receivables/receivables.module.scss';

const axisTick = { fill: 'var(--chart-axis)', fontSize: 12 };
const todayIso = () => format(new Date(), 'yyyy-MM-dd');

function AgingView({ side, asOf, currency }: { side: 'ar' | 'ap'; asOf: string; currency: string }) {
  const companyId = useCompanyId();
  const q = useQuery({
    queryKey: [companyId, 'receivables', 'aging', side, asOf],
    queryFn: () => (side === 'ar' ? receivablesReports.arAging(asOf) : receivablesReports.apAging(asOf)),
  });
  const money = (v: string | number) => formatMoney(v, currency);
  const partnerHref = (id: number) => (side === 'ar' ? `/finance/invoices?f.customer_id=${id}` : `/finance/bills?f.supplier_id=${id}`);

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  const data: AgingReport | undefined = q.data;
  const empty = !!data && data.rows.length === 0;
  const chartRow = data ? [{ name: 'Outstanding', ...Object.fromEntries(AGING_BUCKETS.map((b) => [b.label, Number(data.totals[b.key])])) }] : [];

  const bucketTable = data && (
    <table className={styles.agingTable}>
      <thead><tr><th>Bucket</th><th>Amount</th><th>Share</th></tr></thead>
      <tbody>
        {AGING_BUCKETS.map((b) => (
          <tr key={b.key}>
            <td>{b.label}</td>
            <td>{money(data.totals[b.key])}</td>
            <td>{Number(data.totals.total) ? `${((Number(data.totals[b.key]) / Number(data.totals.total)) * 100).toFixed(1)}%` : '—'}</td>
          </tr>
        ))}
      </tbody>
      <tfoot><tr><td>Total</td><td>{money(data.totals.total)}</td><td /></tr></tfoot>
    </table>
  );

  return (
    <div className={styles.stack}>
      <ChartCard
        title={side === 'ar' ? 'Receivables by age' : 'Payables by age'}
        description={`Outstanding as of ${formatDate(asOf)}, by days past due`}
        legend={AGING_BUCKETS.map((b) => ({ label: b.label, color: b.color }))}
        loading={q.isLoading}
        empty={empty}
        emptyText={side === 'ar' ? 'Nothing outstanding from customers' : 'Nothing owed to suppliers'}
        table={bucketTable}
        height={96}
      >
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={chartRow} layout="vertical" margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
            <XAxis type="number" tickLine={false} axisLine={false} tick={axisTick} tickFormatter={(v: number) => formatCompact(v)} />
            <YAxis type="category" dataKey="name" hide />
            <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
            {AGING_BUCKETS.map((b, i) => (
              <Bar
                key={b.key}
                dataKey={b.label}
                stackId="aging"
                fill={b.color}
                stroke="var(--color-surface)"
                strokeWidth={2}
                maxBarSize={36}
                radius={i === 0 ? [4, 0, 0, 4] : i === AGING_BUCKETS.length - 1 ? [0, 4, 4, 0] : 0}
              />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </ChartCard>

      <Card>
        <CardHeader title={side === 'ar' ? 'By customer' : 'By supplier'} description={data ? `${data.totals.documents} open document${data.totals.documents === 1 ? '' : 's'}` : undefined} />
        <CardBody padding="none">
          {q.isLoading ? (
            <div style={{ padding: 16 }}><Skeleton style={{ height: 200 }} /></div>
          ) : empty || !data ? (
            <EmptyState title="Nothing outstanding" description="Approved, unpaid documents appear here." />
          ) : (
            <div className={styles.scroll}>
              <table className={styles.agingTable} style={{ minWidth: 820 }}>
                <thead>
                  <tr>
                    <th>{side === 'ar' ? 'Customer' : 'Supplier'}</th>
                    <th>Docs</th>
                    {AGING_BUCKETS.map((b) => <th key={b.key}>{b.label}</th>)}
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  {data.rows.map((r) => (
                    <tr key={r.partner_id}>
                      <td><a className={styles.linkBtn} href={partnerHref(r.partner_id)}>{r.partner_name ?? `#${r.partner_id}`}</a></td>
                      <td>{r.documents}</td>
                      {AGING_BUCKETS.map((b) => (
                        <td key={b.key} className={b.key === 'over_90' && Number(r[b.key]) > 0 ? styles.danger : undefined}>
                          {Number(r[b.key]) ? money(r[b.key]) : <span className={styles.hint}>—</span>}
                        </td>
                      ))}
                      <td><strong>{money(r.total)}</strong></td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td>Total</td>
                    <td>{data.totals.documents}</td>
                    {AGING_BUCKETS.map((b) => <td key={b.key}>{money(data.totals[b.key])}</td>)}
                    <td>{money(data.totals.total)}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

function StatementView({ currency }: { currency: string }) {
  const companyId = useCompanyId();
  const customers = usePartners('customer');
  const [customerId, setCustomerId] = useState('');
  const [from, setFrom] = useState(format(startOfYear(new Date()), 'yyyy-MM-dd'));
  const [to, setTo] = useState(todayIso());
  const q = useQuery({
    queryKey: [companyId, 'receivables', 'statement', customerId, from, to],
    queryFn: () => receivablesReports.customerStatement(Number(customerId), from, to),
    enabled: !!customerId,
  });
  const s = q.data;
  const cur = s?.customer.currency ?? currency;
  const money = (v: string) => formatMoney(v, cur);

  return (
    <div className={styles.stack}>
      <div className={styles.controls}>
        <FormField label="Customer">
          <Select value={customerId} placeholder={customers.isLoading ? 'Loading…' : 'Select customer'} options={(customers.data ?? []).map((c) => ({ value: String(c.id), label: partnerLabel(c) }))} onChange={(e) => setCustomerId(e.target.value)} />
        </FormField>
        <FormField label="From"><DateInput value={from} onValueChange={(v) => setFrom(v ?? '')} /></FormField>
        <FormField label="To"><DateInput value={to} min={from || undefined} onValueChange={(v) => setTo(v ?? '')} /></FormField>
        <Button variant="secondary" leftIcon={<Printer size={16} />} disabled={!s} onClick={() => window.print()}>Print</Button>
      </div>
      {!customerId ? (
        <Card><CardBody><EmptyState title="Choose a customer" description="The statement lists posted invoices and receipts with a running balance." /></CardBody></Card>
      ) : q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !s ? (
        <Skeleton style={{ height: 240 }} />
      ) : (
        <Card>
          <CardBody>
            <div className={styles.statementHead}>
              <div>
                <h3>Statement of account</h3>
                <p>{s.customer.name ?? `Customer #${s.customer.id}`}{s.customer.code ? ` · ${s.customer.code}` : ''}</p>
                {s.customer.email && <p>{s.customer.email}</p>}
              </div>
              <div style={{ textAlign: 'right' }}>
                <p>{formatDate(s.from)} – {formatDate(s.to)}</p>
                <p>Closing balance <strong>{money(s.closing_balance)}</strong></p>
              </div>
            </div>
            <div className={styles.scroll}>
              <table className={styles.agingTable} style={{ minWidth: 640 }}>
                <thead><tr><th>Date</th><th style={{ textAlign: 'left' }}>Document</th><th>Debit</th><th>Credit</th><th>Balance</th></tr></thead>
                <tbody>
                  <tr><td>{formatDate(s.from)}</td><td style={{ textAlign: 'left' }}><em>Opening balance</em></td><td /><td /><td>{money(s.opening_balance)}</td></tr>
                  {s.entries.map((e) => (
                    <tr key={`${e.type}-${e.id}`}>
                      <td>{formatDate(e.date)}</td>
                      <td style={{ textAlign: 'left' }}>
                        {e.type === 'invoice' ? <a className={styles.linkBtn} href={`/finance/invoices/${e.id}`}>Invoice {e.number}</a> : <>Receipt {e.number}</>}
                        {e.type === 'invoice' && e.due_date && <span className={styles.hint}> · due {formatDate(e.due_date)}</span>}
                        {e.reference && <span className={styles.hint}> · {e.reference}</span>}
                      </td>
                      <td>{Number(e.debit) ? money(e.debit) : ''}</td>
                      <td>{Number(e.credit) ? money(e.credit) : ''}</td>
                      <td>{money(e.balance)}</td>
                    </tr>
                  ))}
                  {s.entries.length === 0 && <tr><td colSpan={5} style={{ textAlign: 'center' }} className={styles.hint}>No activity in this period</td></tr>}
                </tbody>
                <tfoot>
                  <tr><td /><td style={{ textAlign: 'left' }}>Closing balance</td><td>{money(s.total_debits)}</td><td>{money(s.total_credits)}</td><td>{money(s.closing_balance)}</td></tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

type AgingTab = 'ar' | 'ap' | 'statement';

function AgingInner() {
  const can = useCan();
  const currency = useCurrency();
  const params = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const raw = params.get('tab');
  const tab: AgingTab = raw === 'ap' || raw === 'statement' ? raw : 'ar';
  const [asOf, setAsOf] = useState(todayIso());
  const canReports = can('reports.financial.view');
  const canStatements = canReports || can('invoices.view');

  const setTab = (t: string) => router.replace(`${pathname}?tab=${t}`, { scroll: false });

  if (!canStatements) return <Alert variant="warning">You don’t have permission to view receivables reports.</Alert>;

  return (
    <div className={styles.stack}>
      <PageHeader
        title="Aging & statements"
        description="Outstanding receivables and payables by days past due, and customer statements."
        actions={
          <>
            <Button variant="ghost" href="/finance/invoices">Invoices</Button>
            <Button variant="ghost" href="/finance/bills">Bills</Button>
          </>
        }
      />
      <Tabs value={canReports ? tab : 'statement'} onValueChange={setTab}>
        <div className={`${styles.row} ${styles.noPrint}`}>
          <TabList>
            {canReports && <Tab value="ar">Receivables (AR)</Tab>}
            {canReports && <Tab value="ap">Payables (AP)</Tab>}
            <Tab value="statement">Customer statement</Tab>
          </TabList>
          {canReports && tab !== 'statement' && (
            <div className={styles.controls}>
              <FormField label="As of"><DateInput value={asOf} max={todayIso()} onValueChange={(v) => setAsOf(v ?? todayIso())} /></FormField>
            </div>
          )}
        </div>
        <div style={{ marginTop: 16 }}>
          {canReports && <TabPanel value="ar"><AgingView side="ar" asOf={asOf} currency={currency} /></TabPanel>}
          {canReports && <TabPanel value="ap"><AgingView side="ap" asOf={asOf} currency={currency} /></TabPanel>}
          <TabPanel value="statement"><StatementView currency={currency} /></TabPanel>
        </div>
      </Tabs>
    </div>
  );
}

export default function AgingPage() {
  return (
    <Suspense>
      <AgingInner />
    </Suspense>
  );
}
