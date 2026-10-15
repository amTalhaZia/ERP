'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { format, startOfMonth, startOfYear, subDays, subMonths } from 'date-fns';
import {
  Area, AreaChart, Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis,
} from 'recharts';
import {
  AlertTriangle, ArrowRight, Boxes, CalendarDays, CircleDollarSign, ClipboardList, Clock, FileText, ListChecks, Receipt, TrendingDown, TrendingUp, Users, Wallet,
} from 'lucide-react';
import { Card, CardBody, CardHeader, EmptyState, ErrorState, MetricCard, PageHeader, SegmentedControl, StatusBadge, Skeleton } from '@/components/ui';
import { ChartCard } from '@/components/charts/ChartCard';
import { ChartTooltip } from '@/components/charts/ChartTooltip';
import { dashboardApi, type DashboardPayload, type Metric } from '@/features/dashboard/api';
import { useAppSelector } from '@/store';
import { selectCompany, selectUser } from '@/store/sessionSlice';
import { useCan } from '@/lib/auth/useCan';
import type { Permission } from '@/lib/auth/permissions';
import { isPlatformOnly } from '@/lib/auth/home';
import { formatCompact, formatDate, formatMoney, formatNumber, formatRelative, humanize } from '@/lib/format';
import styles from './dashboard.module.scss';

type RangeKey = '30d' | 'mtd' | '90d' | 'ytd' | '12m';

const RANGES: { value: RangeKey; label: string }[] = [
  { value: 'mtd', label: 'MTD' },
  { value: '30d', label: '30D' },
  { value: '90d', label: '90D' },
  { value: 'ytd', label: 'YTD' },
  { value: '12m', label: '12M' },
];

function rangeDates(key: RangeKey) {
  const today = new Date();
  const from =
    key === 'mtd' ? startOfMonth(today)
    : key === '30d' ? subDays(today, 29)
    : key === '90d' ? subDays(today, 89)
    : key === 'ytd' ? startOfYear(today)
    : subMonths(startOfMonth(today), 11);
  return { from: format(from, 'yyyy-MM-dd'), to: format(today, 'yyyy-MM-dd') };
}

function toChange(m: Metric | undefined, positiveIsGood: boolean) {
  if (!m || m.change_percent === null || m.change_percent === undefined) return undefined;
  const v = m.change_percent;
  return { value: Math.abs(v), direction: v > 0.05 ? 'up' : v < -0.05 ? 'down' : 'flat', positiveIsGood } as const;
}

function num(v: string | number | null | undefined) {
  return v === null || v === undefined ? 0 : Number(v);
}

const QUICK_LINKS: { label: string; description: string; href: string; icon: typeof CalendarDays; permission: Permission[] }[] = [
  { label: 'My leave', description: 'Balances and leave requests', href: '/hr/leave', icon: CalendarDays, permission: ['leave.request', 'leave.view'] },
  { label: 'My payslips', description: 'Download your payslips', href: '/payroll/my-payslips', icon: FileText, permission: ['payslips.view.own'] },
  { label: 'My tasks', description: 'Projects and assigned tasks', href: '/projects', icon: ListChecks, permission: ['tasks.view', 'projects.view'] },
  { label: 'Attendance', description: 'Check-ins and timesheets', href: '/hr/attendance', icon: Clock, permission: ['attendance.view', 'attendance.manage'] },
  { label: 'Expense claims', description: 'Submit and track expenses', href: '/finance/expenses', icon: Receipt, permission: ['expenses.submit', 'expenses.view'] },
  { label: 'Approvals', description: 'Items waiting for your decision', href: '/approvals', icon: ClipboardList, permission: ['approvals.view'] },
];

/** Shown to roles with no dashboard analytics (e.g. employees): shortcuts to self-service pages. */
function QuickLinks({ can }: { can: ReturnType<typeof useCan> }) {
  const links = QUICK_LINKS.filter((l) => can(l.permission, 'any'));
  return (
    <Card>
      <CardHeader title="Quick links" description="Jump to the things you use most." />
      <CardBody>
        {links.length === 0 ? (
          <EmptyState size="sm" title="Nothing to show yet" description="Your role doesn’t include any dashboard content. Ask your administrator if you expected to see more." />
        ) : (
          <ul className={styles.quickLinks}>
            {links.map(({ label, description, href, icon: Icon }) => (
              <li key={href}>
                <Link href={href}>
                  <span className={styles.quickIcon} aria-hidden><Icon size={18} /></span>
                  <span className={styles.listMain}>
                    <span className={styles.listTitle}>{label}</span>
                    <span className={styles.listSub}>{description}</span>
                  </span>
                  <ArrowRight size={16} aria-hidden />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </CardBody>
    </Card>
  );
}

export default function DashboardPage() {
  const can = useCan();
  const user = useAppSelector(selectUser);
  const company = useAppSelector(selectCompany);
  const platformOnly = useAppSelector((s) => isPlatformOnly(s.session));
  const branchId = useAppSelector((s) => s.ui.branchId);
  const [range, setRange] = useState<RangeKey>('mtd');
  const dates = useMemo(() => rangeDates(range), [range]);
  const currency = company?.currency ?? 'USD';

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['dashboard', company?.id, branchId, dates.from, dates.to],
    queryFn: () => dashboardApi.get({ ...dates, branch_id: branchId }),
    enabled: can('dashboard.view') && !platformOnly,
  });
  const d = data?.data;
  const money = (v: string | number | null | undefined) => (v === null || v === undefined ? '—' : formatMoney(v, currency));

  const trend = useMemo(
    () => (d?.trend ?? []).map((p) => ({ period: p.period, Revenue: num(p.revenue), Expenses: num(p.expenses) })),
    [d?.trend],
  );
  const branches = useMemo(
    () => (d?.by_branch ?? []).map((b) => ({ name: b.name, Revenue: num(b.revenue), Expenses: num(b.expenses) })),
    [d?.by_branch],
  );

  // Platform staff outside a company are redirected to the console (SessionProvider).
  if (platformOnly) return null;
  if (!can('dashboard.view')) {
    return (
      <div className={styles.page}>
        <PageHeader title={user?.name ? 'Welcome, ' + user.name.split(' ')[0] : 'Welcome'} description={company?.name} />
        <QuickLinks can={can} />
      </div>
    );
  }

  const m = d?.metrics ?? {};
  // The API omits metrics/sections the user may not see — render only what is present.
  const has = (key: keyof DashboardPayload) => !!d && d[key] !== undefined && d[key] !== null;
  const hasFinance = !!(m.revenue || m.expenses);
  const showBranches = hasFinance && has('by_branch');
  const showInvoices = has('recent_invoices');
  const showApprovals = has('pending_approvals');
  const showLowStock = has('low_stock_items');
  const showDepartments = has('by_department');
  const showActivity = has('recent_activity');
  const richMetrics = Object.keys(m).some((k) => k !== 'pending_approvals');
  const showQuickLinks = !!d && !richMetrics && !showInvoices && !showLowStock && !showDepartments && !showActivity && !showBranches;
  const greeting = (() => {
    const h = new Date().getHours();
    return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
  })();

  const periodLabel = d ? `${formatDate(d.period.from)} – ${formatDate(d.period.to)}` : '';
  const vsLabel = d ? `vs ${formatDate(d.period.previous_from)} – ${formatDate(d.period.previous_to)}` : undefined;

  return (
    <div className={styles.page}>
      <PageHeader
        title={`${greeting}${user?.name ? `, ${user.name.split(' ')[0]}` : ''}`}
        description={company ? `${company.name} · ${periodLabel}` : periodLabel}
        actions={<SegmentedControl<RangeKey> aria-label="Date range" value={range} onChange={setRange} options={RANGES} size="sm" />}
      />

      {error ? (
        <ErrorState error={error} onRetry={() => void refetch()} />
      ) : (
        <>
          <section className={styles.metrics} aria-label="Key metrics">
            {(isLoading || m.revenue) && (
              <MetricCard
                label="Revenue"
                icon={<TrendingUp size={18} />}
                loading={isLoading}
                value={money(m.revenue?.current)}
                unavailable={!isLoading && m.revenue?.current === null}
                change={toChange(m.revenue, true) && { ...toChange(m.revenue, true)!, label: vsLabel }}
                trend={trend.map((t) => t.Revenue)}
                href="/finance/reports"
              />
            )}
            {(isLoading || m.expenses) && (
              <MetricCard
                label="Expenses"
                icon={<TrendingDown size={18} />}
                loading={isLoading}
                value={money(m.expenses?.current)}
                unavailable={!isLoading && m.expenses?.current === null}
                change={toChange(m.expenses, false) && { ...toChange(m.expenses, false)!, label: vsLabel }}
                trend={trend.map((t) => t.Expenses)}
                href="/finance/reports"
              />
            )}
            {(isLoading || m.net_profit) && (
              <MetricCard
                label="Net profit"
                icon={<CircleDollarSign size={18} />}
                loading={isLoading}
                value={money(m.net_profit?.current)}
                unavailable={!isLoading && m.net_profit?.current === null}
                change={toChange(m.net_profit, true) && { ...toChange(m.net_profit, true)!, label: vsLabel }}
                href="/finance/reports"
              />
            )}
            {(isLoading || m.receivables) && (
              <MetricCard label="Outstanding receivables" icon={<Receipt size={18} />} loading={isLoading} value={money(m.receivables?.current)} unavailable={!isLoading && m.receivables?.current === null} href="/finance/invoices" />
            )}
            {(isLoading || m.payables) && (
              <MetricCard label="Outstanding payables" icon={<Wallet size={18} />} loading={isLoading} value={money(m.payables?.current)} unavailable={!isLoading && m.payables?.current === null} href="/finance/bills" />
            )}
            {(isLoading || m.employees) && (
              <MetricCard label="Employees" icon={<Users size={18} />} loading={isLoading} value={num(m.employees?.current)} unavailable={!isLoading && m.employees?.current === null} href="/hr/employees" />
            )}
            {(isLoading || m.active_products) && (
              <MetricCard label="Active products" icon={<Boxes size={18} />} loading={isLoading} value={num(m.active_products?.current)} unavailable={!isLoading && m.active_products?.current === null} href="/inventory/products" />
            )}
            {(isLoading || m.pending_approvals) && (
              <MetricCard label="Pending approvals" icon={<ClipboardList size={18} />} loading={isLoading} value={num(m.pending_approvals?.current)} href="/approvals" />
            )}
          </section>

          {showQuickLinks && <QuickLinks can={can} />}

          {(hasFinance || showBranches) && (
          <section className={styles.grid2}>
            {hasFinance && (
            <ChartCard
              title="Revenue vs expenses"
              description="Posted to the general ledger"
              loading={isLoading}
              empty={!isLoading && trend.every((t) => t.Revenue === 0 && t.Expenses === 0)}
              legend={[
                { label: 'Revenue', color: 'var(--chart-1)' },
                { label: 'Expenses', color: 'var(--chart-3)' },
              ]}
              table={
                <table>
                  <thead>
                    <tr><th>Period</th><th className="num">Revenue</th><th className="num">Expenses</th></tr>
                  </thead>
                  <tbody>
                    {trend.map((t) => (
                      <tr key={t.period}><td>{t.period}</td><td className="num">{money(t.Revenue)}</td><td className="num">{money(t.Expenses)}</td></tr>
                    ))}
                  </tbody>
                </table>
              }
            >
              <ResponsiveContainer width="100%" height="100%">
                <AreaChart data={trend} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
                  <defs>
                    <linearGradient id="gRev" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.18} />
                      <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
                  <XAxis dataKey="period" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} minTickGap={16} />
                  <YAxis tickLine={false} axisLine={false} width={56} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
                  <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ stroke: 'var(--color-border-strong)' }} />
                  <Area type="monotone" dataKey="Revenue" stroke="var(--chart-1)" strokeWidth={2} fill="url(#gRev)" activeDot={{ r: 4 }} />
                  <Area type="monotone" dataKey="Expenses" stroke="var(--chart-3)" strokeWidth={2} fill="transparent" activeDot={{ r: 4 }} />
                </AreaChart>
              </ResponsiveContainer>
            </ChartCard>
            )}

            {showBranches && (
            <ChartCard
              title="By branch"
              description="Revenue and expenses per branch"
              loading={isLoading}
              empty={!isLoading && branches.length === 0}
              legend={[
                { label: 'Revenue', color: 'var(--chart-1)' },
                { label: 'Expenses', color: 'var(--chart-3)' },
              ]}
              table={
                <table>
                  <thead>
                    <tr><th>Branch</th><th className="num">Revenue</th><th className="num">Expenses</th></tr>
                  </thead>
                  <tbody>
                    {branches.map((b) => (
                      <tr key={b.name}><td>{b.name}</td><td className="num">{money(b.Revenue)}</td><td className="num">{money(b.Expenses)}</td></tr>
                    ))}
                  </tbody>
                </table>
              }
            >
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={branches} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} barGap={2}>
                  <CartesianGrid vertical={false} stroke="var(--chart-grid)" />
                  <XAxis dataKey="name" tickLine={false} axisLine={false} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} interval={0} />
                  <YAxis tickLine={false} axisLine={false} width={56} tick={{ fill: 'var(--chart-axis)', fontSize: 12 }} tickFormatter={(v: number) => formatCompact(v)} />
                  <Tooltip content={<ChartTooltip formatValue={(v) => money(v)} />} cursor={{ fill: 'var(--color-surface-hover)' }} />
                  <Bar dataKey="Revenue" fill="var(--chart-1)" radius={[4, 4, 0, 0]} maxBarSize={28} />
                  <Bar dataKey="Expenses" fill="var(--chart-3)" radius={[4, 4, 0, 0]} maxBarSize={28} />
                </BarChart>
              </ResponsiveContainer>
            </ChartCard>
            )}
          </section>
          )}

          {(showInvoices || showApprovals || showLowStock) && (
          <section className={styles.grid3}>
            {showInvoices && (
            <Card>
              <CardHeader title="Recent invoices" actions={<Link href="/finance/invoices" className={styles.more}>View all <ArrowRight size={14} /></Link>} />
              <CardBody padding="none">
                {isLoading ? (
                  <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
                ) : !d?.recent_invoices?.length ? (
                  <EmptyState size="sm" title="No invoices yet" />
                ) : (
                  <ul className={styles.list}>
                    {d.recent_invoices.map((inv) => (
                      <li key={inv.id}>
                        <Link href={`/finance/invoices/${inv.id}`}>
                          <span className={styles.listMain}>
                            <span className={styles.listTitle}>{inv.number}</span>
                            <span className={styles.listSub}>{inv.customer} · {formatDate(inv.date)}</span>
                          </span>
                          <span className={styles.listRight}>
                            <span className="tabular">{money(inv.total)}</span>
                            <StatusBadge status={inv.status} size="sm" />
                          </span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
            )}

            {showApprovals && (
            <Card>
              <CardHeader title="Pending approvals" actions={<Link href="/approvals" className={styles.more}>Inbox <ArrowRight size={14} /></Link>} />
              <CardBody padding="none">
                {isLoading ? (
                  <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
                ) : !d?.pending_approvals?.length ? (
                  <EmptyState size="sm" title="Nothing waiting for you" />
                ) : (
                  <ul className={styles.list}>
                    {d.pending_approvals.map((a) => (
                      <li key={`${a.type}-${a.id}`}>
                        <Link href={a.url}>
                          <span className={styles.listMain}>
                            <span className={styles.listTitle}>{a.title}</span>
                            <span className={styles.listSub}>{humanize(a.type)} · {a.requested_by}</span>
                          </span>
                          <span className={styles.listSub}>{formatRelative(a.created_at)}</span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
            )}

            {showLowStock && (
            <Card>
              <CardHeader
                title="Low stock"
                actions={<Link href="/inventory/products?filter=low-stock" className={styles.more}>View <ArrowRight size={14} /></Link>}
              />
              <CardBody padding="none">
                {isLoading ? (
                  <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
                ) : !d?.low_stock_items?.length ? (
                  <EmptyState size="sm" title="All items above reorder level" />
                ) : (
                  <ul className={styles.list}>
                    {d.low_stock_items.map((p) => (
                      <li key={p.id}>
                        <Link href={`/inventory/products/${p.id}`}>
                          <span className={styles.listMain}>
                            <span className={styles.listTitle}>{p.name}</span>
                            <span className={styles.listSub}>{p.sku}</span>
                          </span>
                          <span className={styles.lowStock}>
                            <AlertTriangle size={14} aria-hidden />
                            <span className="tabular">{formatNumber(p.on_hand)} / {formatNumber(p.reorder_level)}</span>
                          </span>
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
            )}
          </section>
          )}

          {(showDepartments || showActivity) && (
          <section className={styles.grid2}>
            {showDepartments && (
            <Card>
              <CardHeader title="Headcount by department" />
              <CardBody>
                {isLoading ? (
                  <Skeleton style={{ height: 200 }} />
                ) : !d?.by_department?.length ? (
                  <EmptyState size="sm" title="No employees yet" />
                ) : (
                  <ul className={styles.bars}>
                    {(() => {
                      const max = Math.max(...d.by_department.map((x) => x.headcount), 1);
                      return d.by_department.map((dep) => (
                        <li key={dep.name}>
                          <span className={styles.barLabel}>{dep.name}</span>
                          <span className={styles.barTrack}>
                            <span className={styles.barFill} style={{ width: `${(dep.headcount / max) * 100}%` }} />
                          </span>
                          <span className={`${styles.barValue} tabular`}>{dep.headcount}</span>
                        </li>
                      ));
                    })()}
                  </ul>
                )}
              </CardBody>
            </Card>
            )}

            {showActivity && (
            <Card>
              <CardHeader title="Recent activity" actions={can('audit.view') ? <Link href="/audit" className={styles.more}>Audit log <ArrowRight size={14} /></Link> : undefined} />
              <CardBody padding="none">
                {isLoading ? (
                  <div style={{ padding: 16 }}><Skeleton variant="text" lines={6} /></div>
                ) : !d?.recent_activity?.length ? (
                  <EmptyState size="sm" title="No recent activity" />
                ) : (
                  <ul className={styles.activity}>
                    {d.recent_activity.map((a) => (
                      <li key={a.id}>
                        <span className={styles.dot} aria-hidden />
                        <span className={styles.listMain}>
                          <span className={styles.listTitle}>
                            <strong>{a.actor_name ?? 'System'}</strong> {humanize(a.action.split('.').slice(-1)[0] ?? a.action).toLowerCase()} {a.entity_label ?? humanize(a.action.split('.')[0] ?? '')}
                          </span>
                          <span className={styles.listSub}>{formatRelative(a.created_at)}</span>
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </CardBody>
            </Card>
            )}
          </section>
          )}
        </>
      )}
    </div>
  );
}
