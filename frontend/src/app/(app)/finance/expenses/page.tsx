'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Button, DateInput, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { EXPENSE_STATUSES, expenseClaims, type ExpenseClaim } from '@/features/finance/receivables/api';
import crud from '@/components/crud/crud.module.scss';

function ExpensesInner() {
  const router = useRouter();
  const can = useCan();
  const me = useAppSelector(selectUser);
  const list = useListState({ sort: '-claim_date' });
  const query = expenseClaims.useList(list.apiParams);
  const seesAll = can('expenses.view') || can('expenses.approve');

  const columns: DataTableColumn<ExpenseClaim>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={crud.mono}>{row.original.number}</span> },
    { id: 'claimant', header: 'Claimant', cell: ({ row }) => <div className={crud.cellMain}><strong>{row.original.user?.name ?? '—'}</strong><span>{row.original.notes ?? ''}</span></div> },
    { id: 'claim_date', accessorKey: 'claim_date', header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(row.original.claim_date) },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? '—' },
    { id: 'total', accessorKey: 'total', header: 'Total', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
    { id: 'balance_due', header: 'Unpaid', meta: { numeric: true, align: 'right' }, cell: ({ row }) => (row.original.status === 'approved' ? formatMoney(row.original.balance_due, row.original.currency) : <span className={crud.muted}>—</span>) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <ListPage<ExpenseClaim>
      title="Expense claims"
      description={seesAll ? 'Employee out-of-pocket expenses. Approved claims are posted and become payable to the employee.' : 'Your out-of-pocket expense claims.'}
      actions={can('expenses.submit') && <Button leftIcon={<Plus size={16} />} href="/finance/expenses/new">New claim</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number or notes"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={EXPENSE_STATUSES} />
          {seesAll && me && (
            <Select aria-label="Claimant" placeholder="Everyone" value={list.state.filters.user_id ?? ''} onChange={(e) => list.update({ filters: { user_id: e.target.value || undefined } })} options={[{ value: String(me.id), label: 'Only mine' }]} />
          )}
          <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No expense claims"
      emptyAction={can('expenses.submit') ? <Button size="sm" href="/finance/expenses/new">New claim</Button> : undefined}
      onRowClick={(c) => router.push(`/finance/expenses/${c.id}`)}
    />
  );
}

export default function ExpensesPage() {
  return (
    <Suspense>
      <ExpensesInner />
    </Suspense>
  );
}
