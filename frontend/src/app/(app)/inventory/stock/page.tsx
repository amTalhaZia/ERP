'use client';

import { Suspense, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ArrowDownToLine, ArrowUpFromLine, TriangleAlert } from 'lucide-react';
import { Badge, Button, Select, Switch, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatMoney } from '@/lib/format';
import { categories, stockLevels, type StockLevel } from '@/features/inventory/api';
import { StockOperationDrawer, type StockOperation } from '@/features/inventory/StockOperationDrawer';
import { compareDecimals, formatQty } from '@/features/inventory/decimal';
import { useCurrency, useWarehouseOptions } from '@/features/inventory/shared';
import styles from '@/components/crud/crud.module.scss';
import inv from '@/features/inventory/inventory.module.scss';

const isLow = (s: StockLevel) =>
  !!s.product && compareDecimals(s.product.reorder_level, '0') > 0 && compareDecimals(s.quantity_on_hand, s.product.reorder_level) <= 0;

function StockInner() {
  const router = useRouter();
  const can = useCan();
  const currency = useCurrency();
  const list = useListState({ sort: 'product_id' });
  const wh = useWarehouseOptions();
  const cats = categories.useList({ per_page: 100, sort: 'name' });
  const [op, setOp] = useState<{ mode: StockOperation; n: number } | null>(null);

  // /stock/levels takes `category_id` and `below_reorder` as plain params (not filter[...]).
  const { 'filter[category_id]': categoryId, 'filter[below_reorder]': belowReorder, ...rest } = list.apiParams;
  const params: Record<string, unknown> = { ...rest };
  if (categoryId) params.category_id = categoryId;
  if (belowReorder) params.below_reorder = 1;
  const query = stockLevels.useList(params);

  const columns: DataTableColumn<StockLevel>[] = [
    {
      id: 'product',
      header: 'Product',
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong className={isLow(row.original) ? inv.lowRow : undefined}>{row.original.product?.name ?? `#${row.original.product_id}`}</strong>
          <span className={styles.mono}>{row.original.product?.sku}</span>
        </div>
      ),
    },
    {
      id: 'warehouse_id',
      header: 'Warehouse',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.warehouse?.name ?? '—'}</strong>
          <span>{row.original.location ? `${row.original.location.code} · ${row.original.location.name}` : row.original.warehouse?.code}</span>
        </div>
      ),
    },
    {
      id: 'quantity_on_hand',
      header: 'On hand',
      enableSorting: true,
      meta: { numeric: true, align: 'right' },
      cell: ({ row }) => (
        <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
          {isLow(row.original) && <Badge size="sm" variant="warning" icon={<TriangleAlert size={12} />}>Low</Badge>}
          {formatQty(row.original.quantity_on_hand)} <span className={styles.muted}>{row.original.product?.uom?.symbol ?? ''}</span>
        </span>
      ),
    },
    { id: 'reserved', header: 'Reserved', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatQty(row.original.quantity_reserved) },
    { id: 'available', header: 'Available', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatQty(row.original.quantity_available) },
    { id: 'reorder', header: 'Reorder at', meta: { numeric: true, align: 'right' }, cell: ({ row }) => (row.original.product && compareDecimals(row.original.product.reorder_level, '0') > 0 ? formatQty(row.original.product.reorder_level) : <span className={styles.muted}>—</span>) },
    { id: 'average_cost', header: 'Avg. cost', enableSorting: true, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.average_cost, currency) },
    { id: 'value', header: 'Value', meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.stock_value, currency) },
  ];

  return (
    <>
      <ListPage<StockLevel>
        title="Stock levels"
        description="Quantity on hand per product and warehouse, valued at moving average cost."
        actions={
          <>
            {can('inventory.issue') && <Button variant="secondary" leftIcon={<ArrowUpFromLine size={16} />} onClick={() => setOp({ mode: 'issue', n: Date.now() })}>Issue</Button>}
            {can('inventory.receive') && <Button leftIcon={<ArrowDownToLine size={16} />} onClick={() => setOp({ mode: 'receive', n: Date.now() })}>Receive</Button>}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search SKU or product name"
        filters={
          <>
            <Select aria-label="Warehouse" placeholder="All warehouses" value={list.state.filters.warehouse_id ?? ''} onChange={(e) => list.update({ filters: { warehouse_id: e.target.value || undefined } })} options={wh.options} />
            <Select
              aria-label="Category"
              placeholder="All categories"
              value={list.state.filters.category_id ?? ''}
              onChange={(e) => list.update({ filters: { category_id: e.target.value || undefined } })}
              options={(cats.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.parent ? `${c.parent.name} › ${c.name}` : c.name }))}
            />
            <Switch checked={list.state.filters.below_reorder === '1'} onCheckedChange={(on) => list.update({ filters: { below_reorder: on ? '1' : undefined } })} label="Below reorder level" />
          </>
        }
        emptyTitle="No stock on record"
        emptyDescription="Receive stock into a warehouse to see levels here."
        emptyAction={can('inventory.receive') ? <Button leftIcon={<ArrowDownToLine size={16} />} onClick={() => setOp({ mode: 'receive', n: Date.now() })}>Receive stock</Button> : undefined}
        onRowClick={(s) => router.push(`/inventory/products/${s.product_id}`)}
      />
      {op && <StockOperationDrawer key={op.n} open mode={op.mode} onClose={() => setOp(null)} />}
    </>
  );
}

export default function StockLevelsPage() {
  return (
    <Suspense>
      <StockInner />
    </Suspense>
  );
}
