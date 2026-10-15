'use client';

import { useMemo, useState, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { format, startOfMonth, startOfYear } from 'date-fns';
import { CheckCircle2, AlertTriangle, Printer } from 'lucide-react';
import {
  Badge, Button, Card, CardBody, CardHeader, DateInput, EmptyState, ErrorState, PageHeader, Skeleton, Switch, Tab, TabList, TabPanel, Tabs,
} from '@/components/ui';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import { financeReports, type BalanceSheet, type CashFlow, type CashFlowSection, type ProfitAndLoss, type ReportSection, type TrialBalance } from '@/features/finance/api';
import styles from './reports.module.scss';

type Tab = 'pl' | 'bs' | 'tb' | 'cf';

const EMPTY_SECTION: ReportSection = { groups: [], total: '0' };
const EMPTY_CF: CashFlowSection = { lines: [], inflows: '0', outflows: '0', net: '0' };
/** Reports come from the API; never let a missing block crash the page. */
const sec = (s: ReportSection | undefined | null): ReportSection => ({ groups: s?.groups ?? [], total: s?.total ?? '0' });
const cfSec = (s: CashFlowSection | undefined | null): CashFlowSection => (s ? { ...EMPTY_CF, ...s, lines: s.lines ?? [] } : EMPTY_CF);

function Row({ label, value, prev, level = 0, strong, currency }: { label: ReactNode; value: string | undefined; prev?: string; level?: number; strong?: boolean; currency: string }) {
  return (
    <tr className={strong ? styles.strong : undefined}>
      <td style={{ paddingLeft: 12 + level * 18 }}>{label}</td>
      <td className={styles.num}>{formatMoney(value ?? '0', currency)}</td>
      {prev !== undefined && <td className={`${styles.num} ${styles.prev}`}>{formatMoney(prev, currency)}</td>}
    </tr>
  );
}

function Section({ title, section: raw, prev: rawPrev, currency, totalLabel }: { title: string; section: ReportSection | undefined; prev?: ReportSection; currency: string; totalLabel: string }) {
  const section = sec(raw ?? EMPTY_SECTION);
  const prev = rawPrev ? sec(rawPrev) : undefined;
  const prevAmount = (id: number) => prev?.groups.flatMap((g) => g.accounts).find((a) => a.account_id === id)?.amount ?? '0';
  return (
    <>
      <tr className={styles.heading}><td colSpan={prev ? 3 : 2}>{title}</td></tr>
      {section.groups.length === 0 && <tr><td colSpan={prev ? 3 : 2} className={styles.muted} style={{ paddingLeft: 30 }}>No activity</td></tr>}
      {section.groups.map((g) => (
        <GroupRows key={g.subtype ?? 'other'} group={g} prevAmount={prev ? prevAmount : undefined} prevTotal={prev?.groups.find((x) => x.subtype === g.subtype)?.total} currency={currency} />
      ))}
      <Row label={totalLabel} value={section.total} prev={prev?.total} strong currency={currency} />
    </>
  );
}

function GroupRows({ group, prevAmount, prevTotal, currency }: { group: ReportSection['groups'][number]; prevAmount?: (id: number) => string; prevTotal?: string; currency: string }) {
  return (
    <>
      <tr className={styles.subheading}><td colSpan={prevAmount ? 3 : 2} style={{ paddingLeft: 30 }}>{humanize(group.subtype ?? 'Other')}</td></tr>
      {(group.accounts ?? []).map((a) => (
        <Row key={a.account_id} level={2} label={<><span className={styles.code}>{a.code}</span> {a.name}</>} value={a.amount} prev={prevAmount?.(a.account_id)} currency={currency} />
      ))}
      {(group.accounts ?? []).length > 1 && <Row level={1} label={`Total ${humanize(group.subtype ?? 'other').toLowerCase()}`} value={group.total} prev={prevAmount ? prevTotal ?? '0' : undefined} currency={currency} />}
    </>
  );
}

function Statement({ children, compare, title }: { children: ReactNode; compare: boolean; title: string }) {
  return (
    <div className={styles.scroll}>
      <table className={styles.statement} aria-label={title}>
        <thead>
          <tr><th>Account</th><th className={styles.num}>Current</th>{compare && <th className={styles.num}>Comparison</th>}</tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}

function PL({ data, currency, compare }: { data: ProfitAndLoss; currency: string; compare: boolean }) {
  const p = compare ? data.comparison : undefined;
  return (
    <Statement compare={!!p} title="Profit and loss">
      <Section title="Revenue" section={data.revenue} prev={p?.revenue} currency={currency} totalLabel="Total revenue" />
      <Section title="Cost of sales" section={data.cost_of_sales} prev={p?.cost_of_sales} currency={currency} totalLabel="Total cost of sales" />
      <Row label="Gross profit" value={data.gross_profit} prev={p?.gross_profit} strong currency={currency} />
      <Section title="Operating expenses" section={data.expenses} prev={p?.expenses} currency={currency} totalLabel="Total expenses" />
      <tr className={styles.grand}>
        <td>{Number(data.net_profit ?? 0) >= 0 ? 'Net profit' : 'Net loss'}</td>
        <td className={styles.num}>{formatMoney(data.net_profit ?? '0', currency)}</td>
        {p && <td className={styles.num}>{formatMoney(p.net_profit ?? '0', currency)}</td>}
      </tr>
    </Statement>
  );
}

function BS({ data, currency, compare }: { data: BalanceSheet; currency: string; compare: boolean }) {
  const p = compare ? data.comparison : undefined;
  return (
    <>
      <div style={{ marginBottom: 12 }}>
        {data.check?.balanced !== false ? (
          <Badge variant="success"><CheckCircle2 size={12} /> Assets = Liabilities + Equity</Badge>
        ) : (
          <Badge variant="danger"><AlertTriangle size={12} /> Out of balance by {formatMoney(data.check?.difference ?? '0', currency)}</Badge>
        )}
      </div>
      <Statement compare={!!p} title="Balance sheet">
        <Section title="Assets" section={data.assets} prev={p?.assets} currency={currency} totalLabel="Total assets" />
        <Section title="Liabilities" section={data.liabilities} prev={p?.liabilities} currency={currency} totalLabel="Total liabilities" />
        <Section title="Equity" section={data.equity} prev={p?.equity} currency={currency} totalLabel="Equity accounts" />
        <Row level={1} label="Retained earnings (prior years)" value={data.equity?.retained_earnings_prior_years} prev={p?.equity?.retained_earnings_prior_years} currency={currency} />
        <Row level={1} label="Current year earnings" value={data.equity?.current_year_earnings} prev={p?.equity?.current_year_earnings} currency={currency} />
        <Row label="Total equity" value={data.equity?.total_equity} prev={p?.equity?.total_equity} strong currency={currency} />
        <tr className={styles.grand}>
          <td>Total liabilities & equity</td>
          <td className={styles.num}>{formatMoney(data.total_liabilities_and_equity ?? '0', currency)}</td>
          {p && <td className={styles.num}>{formatMoney(p.total_liabilities_and_equity ?? '0', currency)}</td>}
        </tr>
      </Statement>
    </>
  );
}

function TB({ data, currency }: { data: TrialBalance; currency: string }) {
  return (
    <>
      <div style={{ marginBottom: 12 }}>
        {data.totals?.balanced !== false ? <Badge variant="success"><CheckCircle2 size={12} /> Debits equal credits</Badge> : <Badge variant="danger"><AlertTriangle size={12} /> Trial balance does not balance</Badge>}
      </div>
      <div className={styles.scroll}>
        <table className={styles.statement} aria-label="Trial balance">
          <thead><tr><th>Code</th><th>Account</th><th>Type</th><th className={styles.num}>Debit</th><th className={styles.num}>Credit</th><th className={styles.num}>Balance</th></tr></thead>
          <tbody>
            {(data.rows ?? []).map((r) => (
              <tr key={r.account_id}>
                <td className={styles.code}>{r.code}</td><td>{r.name}</td><td>{humanize(r.type)}</td>
                <td className={styles.num}>{formatMoney(r.debit_total, currency)}</td>
                <td className={styles.num}>{formatMoney(r.credit_total, currency)}</td>
                <td className={styles.num}>{formatMoney(r.balance, currency)}</td>
              </tr>
            ))}
            <tr className={styles.grand}><td colSpan={3}>Totals</td><td className={styles.num}>{formatMoney(data.totals?.debit_total ?? '0', currency)}</td><td className={styles.num}>{formatMoney(data.totals?.credit_total ?? '0', currency)}</td><td /></tr>
          </tbody>
        </table>
      </div>
    </>
  );
}

function CFSection({ title, s: raw, currency }: { title: string; s: CashFlowSection | undefined; currency: string }) {
  const s = cfSec(raw);
  return (
    <>
      <tr className={styles.heading}><td colSpan={2}>{title}</td></tr>
      {s.lines.length === 0 && <tr><td colSpan={2} className={styles.muted} style={{ paddingLeft: 30 }}>No cash movements</td></tr>}
      {s.lines.map((l) => <Row key={l.account_id} level={2} label={<><span className={styles.code}>{l.code}</span> {l.name}</>} value={l.amount} currency={currency} />)}
      <Row label={`Net cash from ${title.toLowerCase()}`} value={s.net} strong currency={currency} />
    </>
  );
}

function CF({ data, currency }: { data: CashFlow; currency: string }) {
  return (
    <Statement compare={false} title="Cash flow">
      <Row label="Opening cash & bank" value={data.opening_cash} strong currency={currency} />
      <CFSection title="Operating activities" s={data.operating} currency={currency} />
      <CFSection title="Investing activities" s={data.investing} currency={currency} />
      <CFSection title="Financing activities" s={data.financing} currency={currency} />
      <Row label="Net change in cash" value={data.net_change} strong currency={currency} />
      <tr className={styles.grand}><td>Closing cash & bank</td><td className={styles.num}>{formatMoney(data.closing_cash ?? '0', currency)}</td></tr>
    </Statement>
  );
}

const FETCHERS = {
  pl: financeReports.profitAndLoss,
  bs: financeReports.balanceSheet,
  tb: financeReports.trialBalance,
  cf: financeReports.cashFlow,
} as const;
interface ReportData { pl: ProfitAndLoss; bs: BalanceSheet; tb: TrialBalance; cf: CashFlow }

/**
 * One panel per report, each with its own typed query. Only the active tab's
 * panel is mounted, so a statement can never be rendered with another
 * report's payload (switching tabs used to feed e.g. P&L data to the trial balance).
 */
function ReportPanel<T extends Tab>({ tab, params, currency, compare }: { tab: T; params: Record<string, unknown>; currency: string; compare: boolean }) {
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const q = useQuery<ReportData[T]>({
    queryKey: [companyId, 'finance-report', tab, params],
    queryFn: () => (FETCHERS[tab] as (p: Record<string, unknown>) => Promise<ReportData[T]>)(params),
  });

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (q.isLoading || !q.data) return <Skeleton style={{ height: 360 }} />;
  const data = q.data as ReportData[Tab];
  switch (tab) {
    case 'pl':
      return <PL data={data as ProfitAndLoss} currency={currency} compare={compare} />;
    case 'bs':
      return <BS data={data as BalanceSheet} currency={currency} compare={compare} />;
    case 'tb':
      return (data as TrialBalance).rows?.length ? <TB data={data as TrialBalance} currency={currency} /> : <EmptyState title="No postings yet" />;
    default:
      return <CF data={data as CashFlow} currency={currency} />;
  }
}

export default function FinancialReportsPage() {
  const currency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const branchId = useAppSelector((s) => s.ui.branchId);
  const [tab, setTab] = useState<Tab>('pl');
  const [from, setFrom] = useState(format(startOfYear(new Date()), 'yyyy-MM-dd'));
  const [to, setTo] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [compare, setCompare] = useState(false);
  const ranged = tab === 'pl' || tab === 'cf';
  const comparable = tab === 'pl' || tab === 'bs';

  const params = useMemo(() => {
    const base: Record<string, unknown> = branchId ? { branch_id: branchId } : {};
    if (compare && comparable) base.compare = 'true';
    return ranged ? { ...base, from, to } : { ...base, as_of: to };
  }, [ranged, comparable, from, to, compare, branchId]);

  const titles: Record<Tab, string> = { pl: 'Profit and loss', bs: 'Balance sheet', tb: 'Trial balance', cf: 'Cash flow' };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <PageHeader
        title="Financial reports"
        description="Built from posted general-ledger entries. Amounts in the company base currency."
        actions={<Button variant="secondary" leftIcon={<Printer size={16} />} onClick={() => window.print()}>Print</Button>}
      />
      <Tabs value={tab} onValueChange={(v) => setTab(v as Tab)}>
        <TabList aria-label="Report">
          <Tab value="pl">Profit & loss</Tab>
          <Tab value="bs">Balance sheet</Tab>
          <Tab value="tb">Trial balance</Tab>
          <Tab value="cf">Cash flow</Tab>
        </TabList>
        <Card className={styles.card}>
          <CardHeader
            title={titles[tab]}
            description={ranged ? `${formatDate(from)} – ${formatDate(to)}` : `As of ${formatDate(to)}`}
            actions={
              <div className={styles.controls}>
                {ranged && <DateInput aria-label="From" value={from} onValueChange={(v) => v && setFrom(v)} />}
                <DateInput aria-label={ranged ? 'To' : 'As of'} value={to} onValueChange={(v) => v && setTo(v)} />
                {ranged && <Button size="sm" variant="ghost" onClick={() => { setFrom(format(startOfMonth(new Date()), 'yyyy-MM-dd')); setTo(format(new Date(), 'yyyy-MM-dd')); }}>This month</Button>}
                {comparable && <Switch size="sm" checked={compare} onCheckedChange={setCompare} label="Compare" />}
              </div>
            }
          />
          <CardBody>
            <TabPanel value={tab}>
              <ReportPanel key={tab} tab={tab} params={params} currency={currency} compare={compare && comparable} />
            </TabPanel>
          </CardBody>
        </Card>
      </Tabs>
    </div>
  );
}
