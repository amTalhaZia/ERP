'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Plus, ShoppingCart } from 'lucide-react';
import { Button, DateInput, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { salesOrders, SALES_ORDER_STATUSES, type SalesOrder } from '@/features/sales/api';
import { enumOptions, SalesStatus, useCustomerOptions, useUserOptions } from '@/features/sales/shared';
import styles from '@/components/crud/crud.module.scss';

function OrdersInner() {
  const can = useCan();
  const router = useRouter();
  const list = useListState({ sort: '-order_date' });
  const query = salesOrders.useList(list.apiParams);
  const customerOpts = useCustomerOptions();
  const reps = useUserOptions();
  const canManage = can('sales_orders.manage');

  const columns: DataTableColumn<SalesOrder>[] = [
    {
      id: 'number',
      accessorKey: 'number',
      header: 'Order',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong className={styles.mono}>{row.original.number}</strong>
          <span>{row.original.customer?.name ?? `Customer #${row.original.customer_id}`}</span>
        </div>
      ),
    },
    { id: 'order_date', accessorKey: 'order_date', header: 'Order date', enableSorting: true, cell: ({ row }) => formatDate(row.original.order_date) },
    { id: 'expected_delivery_date', accessorKey: 'expected_delivery_date', header: 'Expected delivery', enableSorting: true, cell: ({ row }) => formatDate(row.original.expected_delivery_date) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <SalesStatus status={row.original.status} /> },
    { id: 'rep', header: 'Sales rep', cell: ({ row }) => row.original.sales_rep?.name ?? <span className={styles.muted}>—</span> },
    { id: 'deliveries', header: 'Deliveries', meta: { numeric: true }, cell: ({ row }) => row.original.deliveries_count ?? 0 },
    { id: 'total', accessorKey: 'total', header: 'Total', enableSorting: true, meta: { numeric: true }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
  ];

  return (
    <ListPage<SalesOrder>
      title="Sales orders"
      description="Confirmed customer orders, approvals and deliveries."
      actions={canManage && <Button leftIcon={<Plus size={16} />} href="/sales/orders/new">New order</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number, ship-to or notes"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={enumOptions(SALES_ORDER_STATUSES)} />
          <Select aria-label="Customer" placeholder="Any customer" value={list.state.filters.customer_id ?? ''} onChange={(e) => list.update({ filters: { customer_id: e.target.value || undefined } })} options={customerOpts.options} />
          <Select aria-label="Sales rep" placeholder="Any sales rep" value={list.state.filters.sales_rep_id ?? ''} onChange={(e) => list.update({ filters: { sales_rep_id: e.target.value || undefined } })} options={reps.options} />
          <DateInput aria-label="Order date from" value={list.state.from ?? null} max={list.state.to} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="Order date to" value={list.state.to ?? null} min={list.state.from} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No sales orders yet"
      emptyDescription="Create an order directly, or convert an accepted quotation."
      emptyAction={canManage ? <Button leftIcon={<ShoppingCart size={16} />} href="/sales/orders/new">Create an order</Button> : undefined}
      onRowClick={(o) => router.push(`/sales/orders/${o.id}`)}
    />
  );
}

export default function SalesOrdersPage() {
  return (
    <Suspense>
      <OrdersInner />
    </Suspense>
  );
}
