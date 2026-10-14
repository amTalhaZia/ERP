'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowRight, Plus } from 'lucide-react';
import { Button, DateInput, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import { transfers, type StockTransfer } from '@/features/inventory/api';
import { useWarehouseOptions } from '@/features/inventory/shared';
import styles from '@/components/crud/crud.module.scss';

const TRANSFER_STATUSES = [
  { value: 'draft', label: 'Draft' },
  { value: 'in_transit', label: 'In transit' },
  { value: 'received', label: 'Received' },
  { value: 'cancelled', label: 'Cancelled' },
];

function TransfersInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-created_at' });
  const query = transfers.useList(list.apiParams);
  const wh = useWarehouseOptions();
  const canTransfer = can('inventory.transfer');

  const columns: DataTableColumn<StockTransfer>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    {
      id: 'route',
      header: 'From → To',
      cell: ({ row }) => (
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
          {row.original.from_warehouse?.code ?? '—'} <ArrowRight size={14} aria-label="to" /> {row.original.to_warehouse?.code ?? '—'}
        </span>
      ),
    },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? 0 },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
    { id: 'shipped_at', accessorKey: 'shipped_at', header: 'Shipped', enableSorting: true, cell: ({ row }) => formatDateTime(row.original.shipped_at) },
    { id: 'received_at', accessorKey: 'received_at', header: 'Received', enableSorting: true, cell: ({ row }) => formatDateTime(row.original.received_at) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} variant={row.original.status === 'in_transit' ? 'warning' : undefined} /> },
  ];

  return (
    <ListPage<StockTransfer>
      title="Stock transfers"
      description="Move stock between warehouses. Shipping deducts from the source; receiving adds to the destination."
      actions={canTransfer && <Button leftIcon={<Plus size={16} />} href="/inventory/transfers/new">New transfer</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number or notes"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={TRANSFER_STATUSES} />
          <Select aria-label="From warehouse" placeholder="Any source" value={list.state.filters.from_warehouse_id ?? ''} onChange={(e) => list.update({ filters: { from_warehouse_id: e.target.value || undefined } })} options={wh.options} />
          <Select aria-label="To warehouse" placeholder="Any destination" value={list.state.filters.to_warehouse_id ?? ''} onChange={(e) => list.update({ filters: { to_warehouse_id: e.target.value || undefined } })} options={wh.options} />
          <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No transfers yet"
      emptyDescription="Create a transfer to move stock between warehouses."
      emptyAction={canTransfer ? <Button leftIcon={<Plus size={16} />} href="/inventory/transfers/new">New transfer</Button> : undefined}
      onRowClick={(t) => router.push(`/inventory/transfers/${t.id}`)}
    />
  );
}

export default function TransfersPage() {
  return (
    <Suspense>
      <TransfersInner />
    </Suspense>
  );
}
