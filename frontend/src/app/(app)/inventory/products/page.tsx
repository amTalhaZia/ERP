'use client';

import { Suspense, useState } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';
import { Eye, Package, Pencil, Plus, Settings2, Trash2, TriangleAlert } from 'lucide-react';
import { Badge, Button, ConfirmDialog, MenuItem, MenuSeparator, Select, StatusBadge, Switch, type DataTableColumn } from '@/components/ui';
import { ListPage } from '@/components/crud/ListPage';
import { useListState } from '@/lib/crud/useListState';
import { useCan } from '@/lib/auth/useCan';
import { formatMoney } from '@/lib/format';
import { PRODUCT_TYPES, categories, lowStockProducts, products, type Product } from '@/features/inventory/api';
import { ProductForm } from '@/features/inventory/ProductForm';
import { compareDecimals, formatQty } from '@/features/inventory/decimal';
import { useCurrency } from '@/features/inventory/shared';
import styles from '@/components/crud/crud.module.scss';

function ProductsInner() {
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const can = useCan();
  const currency = useCurrency();
  const list = useListState({ sort: 'name' });
  // `?f.low_stock=1` (toggle) or `?filter=low-stock` (dashboard deep link).
  const lowStock = list.state.filters.low_stock === '1' || params.get('filter') === 'low-stock';

  const apiParams = { ...list.apiParams };
  delete apiParams['filter[low_stock]'];
  if (lowStock) {
    // The low-stock endpoint only supports category_id filtering.
    delete apiParams['filter[type]'];
    delete apiParams['filter[is_active]'];
  }
  const all = products.useList(apiParams, { enabled: !lowStock });
  const low = lowStockProducts.useList(apiParams, { enabled: lowStock });
  const query = lowStock ? low : all;

  const cats = categories.useList({ per_page: 100, sort: 'name' });
  const del = products.useDelete();
  const [editing, setEditing] = useState<Product | 'new' | null>(null);
  const [deleting, setDeleting] = useState<Product | null>(null);
  const canManage = can('products.manage');

  const setLowStock = (on: boolean) => {
    const next = new URLSearchParams(params.toString());
    next.delete('filter');
    next.delete('page');
    if (on) next.set('f.low_stock', '1');
    else next.delete('f.low_stock');
    const qs = next.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
  };

  const columns: DataTableColumn<Product>[] = [
    { id: 'sku', accessorKey: 'sku', header: 'SKU', enableSorting: true, meta: { width: 140 }, cell: ({ row }) => <span className={styles.mono}>{row.original.sku}</span> },
    {
      id: 'name',
      accessorKey: 'name',
      header: 'Product',
      enableSorting: true,
      cell: ({ row }) => (
        <div className={styles.cellMain}>
          <strong>{row.original.name}</strong>
          <span>{[row.original.category?.name ?? 'Uncategorised', row.original.type === 'service' ? 'Service' : null].filter(Boolean).join(' · ')}</span>
        </div>
      ),
    },
    {
      id: 'on_hand',
      header: 'On hand',
      meta: { numeric: true, align: 'right' },
      cell: ({ row }) => {
        const p = row.original;
        if (p.type === 'service') return <span className={styles.muted}>—</span>;
        const isLow = compareDecimals(p.reorder_level, '0') > 0 && compareDecimals(p.quantity_on_hand ?? '0', p.reorder_level) <= 0;
        return (
          <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center' }}>
            {isLow && <Badge size="sm" variant="warning" icon={<TriangleAlert size={12} />}>Low</Badge>}
            {formatQty(p.quantity_on_hand ?? '0')} <span className={styles.muted}>{p.uom?.symbol ?? ''}</span>
          </span>
        );
      },
    },
    { id: 'cost_price', accessorKey: 'cost_price', header: 'Cost', enableSorting: !lowStock, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.cost_price, currency) },
    { id: 'sale_price', accessorKey: 'sale_price', header: 'Price', enableSorting: !lowStock, meta: { numeric: true, align: 'right' }, cell: ({ row }) => formatMoney(row.original.sale_price, currency) },
    { id: 'status', header: 'Status', cell: ({ row }) => <StatusBadge status={row.original.is_active ? 'active' : 'inactive'} /> },
  ];

  return (
    <>
      <ListPage<Product>
        title={lowStock ? 'Low-stock products' : 'Products'}
        description={lowStock ? 'Active goods at or below their reorder level across all warehouses.' : 'Goods and services you buy, stock and sell.'}
        actions={
          <>
            <Button variant="secondary" href="/inventory/settings" leftIcon={<Settings2 size={16} />}>Categories & units</Button>
            {canManage && <Button leftIcon={<Plus size={16} />} onClick={() => setEditing('new')}>New product</Button>}
          </>
        }
        query={query}
        columns={columns}
        list={list}
        searchPlaceholder="Search SKU, name or barcode"
        filters={
          <>
            <Switch checked={lowStock} onCheckedChange={setLowStock} label="Low stock only" />
            <Select
              aria-label="Category"
              placeholder="All categories"
              value={list.state.filters.category_id ?? ''}
              onChange={(e) => list.update({ filters: { category_id: e.target.value || undefined } })}
              options={(cats.data?.data ?? []).map((c) => ({ value: String(c.id), label: c.parent ? `${c.parent.name} › ${c.name}` : c.name }))}
            />
            {!lowStock && (
              <>
                <Select aria-label="Type" placeholder="Any type" value={list.state.filters.type ?? ''} onChange={(e) => list.update({ filters: { type: e.target.value || undefined } })} options={PRODUCT_TYPES} />
                <Select aria-label="Status" placeholder="Any status" value={list.state.filters.is_active ?? ''} onChange={(e) => list.update({ filters: { is_active: e.target.value || undefined } })} options={[{ value: 'true', label: 'Active' }, { value: 'false', label: 'Inactive' }]} />
              </>
            )}
          </>
        }
        emptyTitle={lowStock ? 'Nothing is running low' : 'No products yet'}
        emptyDescription={lowStock ? 'All stocked products are above their reorder levels.' : 'Add the goods and services you trade.'}
        emptyAction={canManage && !lowStock ? <Button leftIcon={<Package size={16} />} onClick={() => setEditing('new')}>Add a product</Button> : undefined}
        onRowClick={(p) => router.push(`/inventory/products/${p.id}`)}
        rowActions={(p) => (
          <>
            <MenuItem icon={<Eye size={16} />} href={`/inventory/products/${p.id}`}>View</MenuItem>
            {canManage && <MenuItem icon={<Pencil size={16} />} onSelect={() => setEditing(p)}>Edit</MenuItem>}
            {canManage && (
              <>
                <MenuSeparator />
                <MenuItem icon={<Trash2 size={16} />} destructive onSelect={() => setDeleting(p)}>Delete</MenuItem>
              </>
            )}
          </>
        )}
      />

      {canManage && (
        <ProductForm key={editing === null ? 'closed' : editing === 'new' ? 'new' : editing.id} open={editing !== null} product={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />
      )}

      <ConfirmDialog
        open={deleting !== null}
        onClose={() => setDeleting(null)}
        title={`Delete ${deleting?.sku}?`}
        description="Products with stock on hand cannot be deleted — adjust stock to zero or deactivate the product instead."
        destructive
        confirmLabel="Delete product"
        onConfirm={async () => {
          if (deleting) await del.mutateAsync(deleting.id).catch(() => undefined);
          setDeleting(null);
        }}
      />
    </>
  );
}

export default function ProductsPage() {
  return (
    <Suspense>
      <ProductsInner />
    </Suspense>
  );
}
