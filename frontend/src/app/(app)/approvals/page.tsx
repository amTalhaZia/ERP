'use client';

import { useMemo, useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight, CalendarDays, ClipboardList, FileText, Receipt, ShoppingCart, Truck, Wallet } from 'lucide-react';
import { Badge, Card, EmptyState, ErrorState, PageHeader, Pagination, SegmentedControl, Skeleton } from '@/components/ui';
import { get } from '@/lib/api/client';
import { formatMoney, formatRelative, humanize } from '@/lib/format';
import { useAppSelector } from '@/store';
import styles from './approvals.module.scss';

interface ApprovalItem {
  type: string;
  id: number;
  number: string | null;
  title: string;
  requested_by: string | null;
  amount: string | null;
  currency: string | null;
  created_at: string;
  url: string;
}

const ICONS: Record<string, typeof FileText> = {
  leave_request: CalendarDays,
  purchase_requisition: ClipboardList,
  purchase_order: ShoppingCart,
  sales_order: Truck,
  invoice: Receipt,
  bill: FileText,
  expense_claim: Wallet,
  payroll_run: Wallet,
};

export default function ApprovalsPage() {
  const companyId = useAppSelector((s) => s.session.current_company?.id);
  const [type, setType] = useState<string>('all');
  const [page, setPage] = useState(1);

  const counts = useQuery({
    queryKey: [companyId, 'approvals', 'counts'],
    queryFn: () => get<{ data: { total: number; by_type?: Record<string, number> } & Record<string, unknown> }>('/approvals/counts').then((r) => r.data),
  });
  const params = { page, per_page: 25, ...(type !== 'all' ? { 'filter[type]': type } : {}) };
  const list = useQuery({
    queryKey: [companyId, 'approvals', 'list', params],
    queryFn: () => get<{ data: ApprovalItem[]; meta?: { pagination: { page: number; per_page: number; total: number; last_page: number } } }>('/approvals', params),
  });

  // Counts payload: { total, by_type: {...} } or flat {type: n, total}. Support both.
  const byType = useMemo(() => {
    const d = counts.data;
    if (!d) return {} as Record<string, number>;
    const src = (d.by_type ?? d) as Record<string, unknown>;
    return Object.fromEntries(Object.entries(src).filter(([k, v]) => k !== 'total' && typeof v === 'number' && v > 0)) as Record<string, number>;
  }, [counts.data]);

  const options = [
    { value: 'all', label: `All${counts.data ? ` (${counts.data.total})` : ''}` },
    ...Object.entries(byType).map(([k, n]) => ({ value: k, label: `${humanize(k)} (${n})` })),
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 1000 }}>
      <PageHeader title="Approvals" description="Requests waiting for your decision. Your own requests never appear here." />
      {options.length > 1 && (
        <div style={{ overflowX: 'auto' }}>
          <SegmentedControl aria-label="Filter by type" size="sm" value={type} onChange={(v) => { setType(v); setPage(1); }} options={options} />
        </div>
      )}
      {list.error ? (
        <ErrorState error={list.error} onRetry={() => void list.refetch()} />
      ) : list.isLoading ? (
        <Skeleton style={{ height: 320 }} />
      ) : !list.data?.data.length ? (
        <EmptyState icon={<ClipboardList size={22} />} title="Nothing waiting for you" description="Leave requests, purchase approvals, invoices, bills and expense claims that need your approval will appear here." />
      ) : (
        <Card>
          <ul className={styles.list}>
            {list.data.data.map((a) => {
              const Icon = ICONS[a.type] ?? FileText;
              return (
                <li key={`${a.type}-${a.id}`}>
                  <Link href={a.url} className={styles.row}>
                    <span className={styles.icon} aria-hidden><Icon size={18} /></span>
                    <span className={styles.main}>
                      <span className={styles.title}>{a.title}</span>
                      <span className={styles.sub}>
                        <Badge size="sm" variant="neutral">{humanize(a.type)}</Badge>
                        {a.number && <span className={styles.mono}>{a.number}</span>}
                        {a.requested_by && <span>by {a.requested_by}</span>}
                        <span>{formatRelative(a.created_at)}</span>
                      </span>
                    </span>
                    {a.amount && <span className={styles.amount}>{formatMoney(a.amount, a.currency ?? 'USD')}</span>}
                    <ArrowRight size={16} className={styles.arrow} aria-hidden />
                  </Link>
                </li>
              );
            })}
          </ul>
        </Card>
      )}
      {list.data?.meta && list.data.meta.pagination.last_page > 1 && (
        <Pagination page={list.data.meta.pagination.page} lastPage={list.data.meta.pagination.last_page} total={list.data.meta.pagination.total} perPage={list.data.meta.pagination.per_page} onPageChange={setPage} />
      )}
    </div>
  );
}
