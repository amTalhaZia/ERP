'use client';

import { Suspense } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Alert, DateInput, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { formatDate, formatDateTime } from '@/lib/format';
import { deliveries, DELIVERY_STATUSES, type Delivery } from '@/features/sales/api';
import { enumOptions, SalesStatus } from '@/features/sales/shared';
import styles from '@/components/crud/crud.module.scss';

function DeliveriesInner() {
  const router = useRouter();
  const list = useListState({ sort: '-delivery_date' });
  const query = deliveries.useList(list.apiParams);

  const columns: DataTableColumn<Delivery>[] = [
    { id: 'number', accessorKey: 'number', header: 'Delivery', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    {
      id: 'order',
      header: 'Sales order',
      cell: ({ row }) => (
        <Link href={`/sales/orders/${row.original.sales_order_id}`} onClick={(e) => e.stopPropagation()} className={styles.mono}>
          {row.original.sales_order?.number ?? `#${row.original.sales_order_id}`}
        </Link>
      ),
    },
    { id: 'delivery_date', accessorKey: 'delivery_date', header: 'Date', enableSorting: true, cell: ({ row }) => formatDate(row.original.delivery_date) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    {
      id: 'carrier',
      header: 'Carrier / tracking',
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.carrier ?? '—'}</strong>
          <span>{row.original.tracking_number ?? 'No tracking number'} · manual</span>
        </div>
      ),
    },
    { id: 'shipped_at', accessorKey: 'shipped_at', header: 'Shipped', enableSorting: true, cell: ({ row }) => formatDateTime(row.original.shipped_at) },
    { id: 'delivered_at', accessorKey: 'delivered_at', header: 'Delivered', enableSorting: true, cell: ({ row }) => formatDateTime(row.original.delivered_at) },
  ];

  return (
    <ListPage<Delivery>
      title="Deliveries"
      description="Shipments against sales orders. Manage them from the order page."
      above={<Alert variant="info">Tracking is manual — carrier and tracking numbers are entered by hand; there is no live GPS feed.</Alert>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number, carrier or tracking no."
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={enumOptions(DELIVERY_STATUSES)} />
          <DateInput aria-label="Delivery date from" value={list.state.from ?? null} max={list.state.to} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="Delivery date to" value={list.state.to ?? null} min={list.state.from} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No deliveries yet"
      emptyDescription={<>Deliveries are created from approved <Link href="/sales/orders">sales orders</Link>.</>}
      onRowClick={(d) => router.push(`/sales/orders/${d.sales_order_id}`)}
    />
  );
}

export default function DeliveriesPage() {
  return (
    <Suspense>
      <DeliveriesInner />
    </Suspense>
  );
}
