'use client';

import { Suspense } from 'react';
import { useRouter } from 'next/navigation';
import { Plus } from 'lucide-react';
import { Button, DateInput, Select, StatusBadge, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import { ADJUSTMENT_REASONS, adjustments, type StockAdjustment } from '@/features/inventory/api';
import { useWarehouseOptions } from '@/features/inventory/shared';
import styles from '@/components/crud/crud.module.scss';

const reasonLabel = (r: string) => ADJUSTMENT_REASONS.find((x) => x.value === r)?.label ?? r;

function AdjustmentsInner() {
  const router = useRouter();
  const can = useCan();
  const list = useListState({ sort: '-created_at' });
  const query = adjustments.useList(list.apiParams);
  const wh = useWarehouseOptions();
  const canAdjust = can('inventory.adjust');

  const columns: DataTableColumn<StockAdjustment>[] = [
    { id: 'number', accessorKey: 'number', header: 'Number', enableSorting: true, cell: ({ row }) => <span className={styles.mono}>{row.original.number}</span> },
    {
      id: 'warehouse',
      header: 'Warehouse',
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.warehouse?.name ?? '—'}</strong>
          <span>{row.original.notes ?? ''}</span>
        </div>
      ),
    },
    { id: 'reason', accessorKey: 'reason', header: 'Reason', enableSorting: true, cell: ({ row }) => reasonLabel(row.original.reason) },
    { id: 'lines', header: 'Lines', meta: { numeric: true, align: 'right' }, cell: ({ row }) => row.original.lines_count ?? 0 },
    { id: 'created_at', accessorKey: 'created_at', header: 'Created', enableSorting: true, cell: ({ row }) => formatDate(row.original.created_at) },
    { id: 'posted_at', accessorKey: 'posted_at', header: 'Posted', enableSorting: true, cell: ({ row }) => formatDateTime(row.original.posted_at) },
    { id: 'status', accessorKey: 'status', header: 'Status', enableSorting: true, cell: ({ row }) => <StatusBadge status={row.original.status} /> },
  ];

  return (
    <ListPage<StockAdjustment>
      title="Stock adjustments"
      description="Correct quantities after counts, damage, loss or expiry. Posting writes the difference to the stock ledger."
      actions={canAdjust && <Button leftIcon={<Plus size={16} />} href="/inventory/adjustments/new">New adjustment</Button>}
      query={query}
      columns={columns}
      list={list}
      searchPlaceholder="Search number or notes"
      filters={
        <>
          <Select aria-label="Status" placeholder="Any status" value={list.state.filters.status ?? ''} onChange={(e) => list.update({ filters: { status: e.target.value || undefined } })} options={[{ value: 'draft', label: 'Draft' }, { value: 'posted', label: 'Posted' }]} />
          <Select aria-label="Reason" placeholder="Any reason" value={list.state.filters.reason ?? ''} onChange={(e) => list.update({ filters: { reason: e.target.value || undefined } })} options={ADJUSTMENT_REASONS} />
          <Select aria-label="Warehouse" placeholder="All warehouses" value={list.state.filters.warehouse_id ?? ''} onChange={(e) => list.update({ filters: { warehouse_id: e.target.value || undefined } })} options={wh.options} />
          <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </>
      }
      emptyTitle="No adjustments yet"
      emptyDescription="Record a stock count or write-off to correct quantities."
      emptyAction={canAdjust ? <Button leftIcon={<Plus size={16} />} href="/inventory/adjustments/new">New adjustment</Button> : undefined}
      onRowClick={(a) => router.push(`/inventory/adjustments/${a.id}`)}
    />
  );
}

export default function AdjustmentsPage() {
  return (
    <Suspense>
      <AdjustmentsInner />
    </Suspense>
  );
}
