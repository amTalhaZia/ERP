'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { FilePlus2, Plus } from 'lucide-react';
import { Button, DateInput, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { quotations, QUOTATION_STATUSES, type Quotation } from '@/features/sales/api';
import { enumOptions, SalesStatus, useCustomerOptions, useUserOptions } from '@/features/sales/shared';
import styles from '@/components/crud/crud.module.scss';

function QuotationsInner() {
  const can = useCan();
  const router = useRouter();
  const list = useListState({ sort: '-date' });
  const query = quotations.useList(list.apiParams);
  const customerOpts = useCustomerOptions();
  const reps = useUserOptions();
  const canManage = can('quotations.manage');
  const today = new Date().toISOString().slice(0, 10);

  const columns: DataTableColumn<Quotation>[] = [
    {
      id: 'number',
      accessorKey: 'number',
      header: 'Quotation',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong className={styles.mono}>{row.original.number}</strong>
          <span>{row.original.customer?.name ?? `Customer #${row.original.customer_id}`}</span>
        </div>
      ),
    },
    { id: 'date', accessorKey: 'date', header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(row.original.date) },
    {
      id: 'valid_until',
      accessorKey: 'valid_until',
      header: 'Valid until',
      enableSorting: true,
      cell: ({ row }) => {
        const v = row.original.valid_until;
        const lapsed = v && v < today && (row.original.status === 'draft' || row.original.status === 'sent');
        return <span style={lapsed ? { color: 'var(--color-warning-text)' } : undefined}>{formatDate(v)}</span>;
      },
    },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    { id: 'rep', header: 'Sales rep', cell: ({ row }) => row.original.sales_rep?.name ?? <span className={styles.muted}>—</span> },
    { id: 'lines', header: 'Lines', meta: { numeric: true }, cell: ({ row }) => row.original.lines_count ?? '—' },
    { id: 'total', accessorKey: 'total', header: 'Total', enableSorting: true, meta: { numeric: true }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
  ];

  return (
    <ListPage<Quotation>
      title="Quotations"
      description="Price offers to customers. Accepted quotations convert into sales orders."
      actions={canManage && <Button leftIcon={<Plus size={16} />} href="/sales/quotations/new">New quotation</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number or notes"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={enumOptions(QUOTATION_STATUSES)} />
          <Select aria-label="Customer" placeholder="Any customer" value={list.state.filters.customer_id ?? ''} onChange={(e) => list.update({ filters: { customer_id: e.target.value || undefined } })} options={customerOpts.options} />
          <Select aria-label="Sales rep" placeholder="Any sales rep" value={list.state.filters.sales_rep_id ?? ''} onChange={(e) => list.update({ filters: { sales_rep_id: e.target.value || undefined } })} options={reps.options} />
          <DateInput aria-label="Date from" value={list.state.from ?? null} max={list.state.to} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="Date to" value={list.state.to ?? null} min={list.state.from} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No quotations yet"
      emptyDescription="Create a quotation to send prices to a customer."
      emptyAction={canManage ? <Button leftIcon={<FilePlus2 size={16} />} href="/sales/quotations/new">Create a quotation</Button> : undefined}
      onRowClick={(q) => router.push(`/sales/quotations/${q.id}`)}
    />
  );
}

export default function QuotationsPage() {
  return (
    <Suspense>
      <QuotationsInner />
    </Suspense>
  );
}
