'use client';

import { Suspense } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Inbox } from 'lucide-react';
import { DataTable, DateInput, EmptyState, FilterBar, PageHeader, Select, paginationFromMeta, type DataTableColumn } from '@/components/ui';
import { useListState } from '@/lib/crud/useListState';
import { formatDateTime, formatMoney, humanize } from '@/lib/format';
import { MOVEMENT_TYPES, products, stockMovements, type StockMovement } from '@/features/inventory/api';
import { formatQty } from '@/features/inventory/decimal';
import { ProductPicker, SignedQty, useCurrency, useWarehouseOptions } from '@/features/inventory/shared';
import styles from '@/components/crud/crud.module.scss';

const REFERENCE_HREF: Record<string, string> = {
  stock_transfer: '/inventory/transfers',
  stock_adjustment: '/inventory/adjustments',
};

function MovementsInner() {
  const router = useRouter();
  const currency = useCurrency();
  const list = useListState({ sort: '-moved_at' });
  const query = stockMovements.useList(list.apiParams);
  const wh = useWarehouseOptions();
  const productId = list.state.filters.product_id;
  const selected = products.useOne(productId, { enabled: !!productId });
  const meta = query.data?.meta?.pagination;
  const filtered = list.activeFilterCount > 0;

  const columns: DataTableColumn<StockMovement>[] = [
    { id: 'moved_at', accessorKey: 'moved_at', header: 'Date', enableSorting: true, meta: { width: 160 }, cell: ({ row }) => formatDateTime(row.original.moved_at) },
    {
      id: 'product',
      header: 'Product',
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.product?.name ?? `#${row.original.product_id}`}</strong>
          <span className={styles.mono}>{row.original.product?.sku}</span>
        </div>
      ),
    },
    {
      id: 'type',
      header: 'Type',
      cell: ({ row }) => {
        const m = row.original;
        const base = m.reference_type ? REFERENCE_HREF[m.reference_type] : undefined;
        return (
          <div className={styles.cellMain}>
            <strong>{humanize(m.type)}</strong>
            <span>
              {base && m.reference_id && m.reference_type ? (
                <Link href={`${base}/${m.reference_id}`} onClick={(e) => e.stopPropagation()}>{humanize(m.reference_type)} #{m.reference_id}</Link>
              ) : (
                m.reason ?? ''
              )}
            </span>
          </div>
        );
      },
    },
    { id: 'warehouse', header: 'Warehouse', cell: ({ row }) => <span title={row.original.warehouse?.name}>{row.original.warehouse?.code ?? '—'}</span> },
    { id: 'quantity', accessorKey: 'quantity', header: 'Quantity', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => <SignedQty value={row.original.quantity} /> },
    { id: 'unit_cost', header: 'Unit cost', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.unit_cost, currency) },
    { id: 'balance_after', header: 'Balance after', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatQty(row.original.balance_after) },
    { id: 'creator', header: 'By', cell: ({ row }) => row.original.creator?.name ?? <span className={styles.muted}>System</span> },
  ];

  // /stock/movements has no free-text search, so this page composes the list
  // primitives directly instead of ListPage (which always renders a search box).
  return (
    <div className={styles.page}>
      <PageHeader title="Stock movements" description="The immutable stock ledger: every receipt, issue, transfer and adjustment." />
      <div className={styles.toolbar}>
        <FilterBar activeCount={list.activeFilterCount} onClear={list.clearFilters}>
          <Select aria-label="Type" placeholder="All types" value={list.state.filters.type ?? ''} onChange={(e) => list.update({ filters: { type: e.target.value || undefined } })} options={MOVEMENT_TYPES.map((t) => ({ value: t, label: humanize(t) }))} />
          <Select aria-label="Warehouse" placeholder="All warehouses" value={list.state.filters.warehouse_id ?? ''} onChange={(e) => list.update({ filters: { warehouse_id: e.target.value || undefined } })} options={wh.options} />
          <div style={{ minWidth: 240 }}>
            <ProductPicker
              aria-label="Product"
              goodsOnly={false}
              value={productId ? (selected.data ?? { id: Number(productId), sku: `#${productId}`, name: '' }) : null}
              onChange={(p) => list.update({ filters: { product_id: p ? String(p.id) : undefined } })}
            />
          </div>
          <DateInput aria-label="From" value={list.state.from ?? ''} onValueChange={(v) => list.update({ from: v ?? undefined })} />
          <DateInput aria-label="To" value={list.state.to ?? ''} onValueChange={(v) => list.update({ to: v ?? undefined })} />
        </FilterBar>
      </div>
      <DataTable<StockMovement>
        aria-label="Stock movements"
        columns={columns}
        data={query.data?.data ?? []}
        isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
        error={query.error}
        onRetry={() => void query.refetch()}
        sort={list.state.sort}
        onSortChange={(sort) => list.update({ sort })}
        pagination={
          meta
            ? {
                ...paginationFromMeta(meta),
                onPageChange: (page: number) => list.update({ page }, false),
                onPerPageChange: (perPage: number) => list.update({ perPage }),
              }
            : undefined
        }
        onRowClick={(m: StockMovement) => router.push(`/inventory/products/${m.product_id}`)}
        enableColumnVisibility
        emptyState={
          filtered ? (
            <EmptyState icon={<Inbox size={22} />} title="No matching movements" description="Try different filters." action={<button type="button" className={styles.linkBtn} onClick={list.clearFilters}>Clear filters</button>} />
          ) : (
            <EmptyState icon={<Inbox size={22} />} title="No stock movements yet" description="Movements are recorded automatically when stock is received, issued, transferred or adjusted." />
          )
        }
      />
    </div>
  );
}

export default function MovementsPage() {
  return (
    <Suspense>
      <MovementsInner />
    </Suspense>
  );
}
