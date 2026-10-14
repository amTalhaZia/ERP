'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Button, DateInput, Select, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney } from '@/lib/format';
import { PO_STATUSES, purchaseOrders, suppliers, type PurchaseOrder, type SupplierOption } from '@/features/procurement/api';
import { DocStatus } from '@/features/procurement/components';
import styles from '@/components/crud/crud.module.scss';

function OrdersInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-order_date' });
  const query = purchaseOrders.useList(list.apiParams);
  const supplierList = suppliers.useAll<SupplierOption>('all', {}, { enabled: can('suppliers.view') });
  const today = new Date().toISOString().slice(0, 10);

  const columns: DataTableColumn<PurchaseOrder>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    {
      id: 'supplier',
      header: 'Supplier',
      enableHiding: false,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.supplier?.name ?? '—'}</strong>
          <span>{row.original.supplier?.code ?? ''}</span>
        </div>
      ),
    },
    { id: 'order_date', accessorKey: 'order_date', header: 'Order date', enableSorting: true, cell: ({ row }) => formatDate(row.original.order_date) },
    {
      id: 'expected_date',
      accessorKey: 'expected_date',
      header: 'Expected',
      enableSorting: true,
      cell: ({ row }) => {
        const o = row.original;
        const late = o.expected_date && o.expected_date < today && ['approved', 'partially_received'].includes(o.status);
        return <span style={late ? { color: 'var(--color-danger-text)' } : undefined}>{formatDate(o.expected_date)}{late ? ' · late' : ''}</span>;
      },
    },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? 0 },
    { id: 'total', accessorKey: 'total', header: 'Total', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.total, row.original.currency) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <DocStatus status={row.original.status} /> },
  ];

  return (
    <ListPage<PurchaseOrder>
      title="Purchase orders"
      description="Orders placed with suppliers, from draft through approval, receipt and close."
      actions={can('purchase_orders.manage') && <Button leftIcon={<Plus size={16} />} href="/procurement/orders/new">New purchase order</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number, notes or address"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={PO_STATUSES} />
          <Select
            aria-label="Supplier"
            placeholder="Any supplier"
            value={list.state.filters.supplier_id ?? ''}
            onChange={(e) => list.update({ filters: { supplier_id: e.target.value || undefined } })}
            options={(supplierList.data ?? []).map((s) => ({ value: String(s.id), label: s.name }))}
          />
          <DateInput aria-label="Order date from" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="Order date to" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No purchase orders yet"
      emptyDescription="Create one directly or convert an approved requisition."
      emptyAction={can('purchase_orders.manage') ? <Button leftIcon={<Plus size={16} />} href="/procurement/orders/new">New purchase order</Button> : undefined}
      onRowClick={(o) => router.push(`/procurement/orders/${o.id}`)}
    />
  );
}

export default function OrdersPage() {
  return (
    <Suspense>
      <OrdersInner />
    </Suspense>
  );
}
