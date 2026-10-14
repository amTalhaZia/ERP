'use client';

import { use, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDownToLine, ArrowUpFromLine, ImagePlus, Package, Pencil, Trash2 } from 'lucide-react';
import {
  Badge, Button, Card, CardBody, CardHeader, ConfirmDialog, EmptyState, ErrorState, PageHeader, Pagination, Select, Skeleton, SkeletonCard, StatusBadge, paginationFromMeta, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { formatDate, formatDateTime, formatMoney, formatPercent, humanize } from '@/lib/format';
import { MOVEMENT_TYPES, products, stockApi, type Product } from '@/features/inventory/api';
import { ProductForm } from '@/features/inventory/ProductForm';
import { StockOperationDrawer, type StockOperation } from '@/features/inventory/StockOperationDrawer';
import { compareDecimals, formatQty, sumDecimals } from '@/features/inventory/decimal';
import { SignedQty, useCompanyId, useCurrency } from '@/features/inventory/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

function StockCard({ product }: { product: Product }) {
  const companyId = useCompanyId();
  const currency = useCurrency();
  const q = useQuery({ queryKey: [companyId, 'product-stock', product.id], queryFn: () => stockApi.productStock(product.id) });
  const rows = q.data ?? [];
  const uom = product.uom?.symbol ?? '';

  return (
    <Card>
      <CardHeader title="Stock by warehouse" description={`Total on hand: ${formatQty(product.quantity_on_hand ?? '0')} ${uom}`} />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><Skeleton variant="text" lines={3} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" icon={<Package size={20} />} title="No stock recorded" description="Receive stock to start tracking quantities." />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 560 }}>
              <thead>
                <tr><th>Warehouse</th><th>Location</th><th className={crud.r}>On hand</th><th className={crud.r}>Reserved</th><th className={crud.r}>Available</th><th className={crud.r}>Avg. cost</th><th className={crud.r}>Value</th></tr>
              </thead>
              <tbody>
                {rows.map((s) => (
                  <tr key={s.id}>
                    <td><span className={crud.mono}>{s.warehouse?.code}</span> {s.warehouse?.name}</td>
                    <td>{s.location ? `${s.location.code}` : <span className={crud.muted}>—</span>}</td>
                    <td className={crud.r}>{formatQty(s.quantity_on_hand)}</td>
                    <td className={crud.r}>{formatQty(s.quantity_reserved)}</td>
                    <td className={crud.r}>{formatQty(s.quantity_available)}</td>
                    <td className={crud.r}>{formatMoney(s.average_cost, currency)}</td>
                    <td className={crud.r}>{formatMoney(s.stock_value, currency)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr>
                  <td colSpan={2}>Total</td>
                  <td className={crud.r}>{formatQty(sumDecimals(rows.map((r) => r.quantity_on_hand)))}</td>
                  <td className={crud.r}>{formatQty(sumDecimals(rows.map((r) => r.quantity_reserved)))}</td>
                  <td className={crud.r}>{formatQty(sumDecimals(rows.map((r) => r.quantity_available)))}</td>
                  <td />
                  <td className={crud.r}>{formatMoney(sumDecimals(rows.map((r) => r.stock_value)), currency)}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function MovementsCard({ productId }: { productId: number }) {
  const companyId = useCompanyId();
  const currency = useCurrency();
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [type, setType] = useState('');
  const params: Record<string, unknown> = { page, per_page: perPage, sort: '-moved_at', ...(type ? { 'filter[type]': type } : {}) };
  const q = useQuery({
    queryKey: [companyId, 'product-movements', productId, params],
    queryFn: () => stockApi.productMovements(productId, params),
    placeholderData: keepPreviousData,
  });
  const rows = q.data?.data ?? [];
  const meta = q.data?.meta?.pagination;

  return (
    <Card>
      <CardHeader
        title="Movement history"
        actions={
          <Select
            aria-label="Movement type"
            size="sm"
            placeholder="All types"
            value={type}
            onChange={(e) => {
              setType(e.target.value);
              setPage(1);
            }}
            options={MOVEMENT_TYPES.map((t) => ({ value: t, label: humanize(t) }))}
          />
        }
      />
      <CardBody padding="none">
        {q.error ? (
          <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />
        ) : q.isLoading ? (
          <div style={{ padding: 16 }}><Skeleton variant="text" lines={5} /></div>
        ) : rows.length === 0 ? (
          <EmptyState size="sm" title="No movements" description={type ? 'No movements of this type.' : 'Receipts, issues, transfers and adjustments will appear here.'} />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 720 }}>
              <thead>
                <tr><th>Date</th><th>Type</th><th>Warehouse</th><th>Batch</th><th className={crud.r}>Quantity</th><th className={crud.r}>Unit cost</th><th className={crud.r}>Balance</th><th>By</th></tr>
              </thead>
              <tbody>
                {rows.map((m) => (
                  <tr key={m.id}>
                    <td>{formatDateTime(m.moved_at)}</td>
                    <td>{humanize(m.type)}{m.reason ? <div className={crud.muted}>{m.reason}</div> : null}</td>
                    <td>{m.warehouse?.code ?? '—'}</td>
                    <td>{m.batch ? `${m.batch.batch_number}${m.batch.expires_at ? ` · exp ${formatDate(m.batch.expires_at)}` : ''}` : <span className={crud.muted}>—</span>}</td>
                    <td className={crud.r}><SignedQty value={m.quantity} /></td>
                    <td className={crud.r}>{formatMoney(m.unit_cost, currency)}</td>
                    <td className={crud.r}>{formatQty(m.balance_after)}</td>
                    <td>{m.creator?.name ?? <span className={crud.muted}>System</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {meta && meta.total > 0 && (
          <div style={{ padding: 12 }}>
            <Pagination {...paginationFromMeta(meta)} onPageChange={setPage} onPerPageChange={(n) => { setPerPage(n); setPage(1); }} itemLabel="movements" />
          </div>
        )}
      </CardBody>
    </Card>
  );
}

function ImageUpload({ product }: { product: Product }) {
  const qc = useQueryClient();
  const toast = useToast();
  const input = useRef<HTMLInputElement>(null);
  const upload = useMutation<Product, ApiError, File>({
    mutationFn: (file) => stockApi.uploadImage(product.id, file),
    onSuccess: () => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('products') });
      toast.success('Image updated');
    },
    onError: (e) => toast.error('Upload failed', e.errors?.image?.[0] ?? e.message),
  });
  return (
    <>
      <input
        ref={input}
        type="file"
        accept="image/png,image/jpeg,image/webp"
        hidden
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) upload.mutate(f);
          e.target.value = '';
        }}
      />
      <Button size="sm" variant="secondary" leftIcon={<ImagePlus size={14} />} loading={upload.isPending} onClick={() => input.current?.click()}>
        {product.image_url ? 'Replace image' : 'Upload image'}
      </Button>
    </>
  );
}

export default function ProductPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const currency = useCurrency();
  const q = products.useOne(id);
  const del = products.useDelete({ onSuccess: () => router.replace('/inventory/products') });
  const [editing, setEditing] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [op, setOp] = useState<{ mode: StockOperation; n: number } | null>(null);
  const canManage = can('products.manage');

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const p = q.data;
  const goods = p.type === 'goods';
  const isLow = goods && compareDecimals(p.reorder_level, '0') > 0 && compareDecimals(p.quantity_on_hand ?? '0', p.reorder_level) <= 0;

  return (
    <div className={styles.stack}>
      <PageHeader
        title={p.name}
        description={<span className={crud.mono}>{p.sku}{p.barcode ? ` · ${p.barcode}` : ''}</span>}
        backHref="/inventory/products"
        backLabel="Products"
        meta={
          <span className={styles.inline}>
            <StatusBadge status={p.is_active ? 'active' : 'inactive'} />
            {isLow && <Badge variant="warning">Below reorder level</Badge>}
          </span>
        }
        actions={
          <>
            {goods && p.is_active && can('inventory.receive') && <Button variant="secondary" leftIcon={<ArrowDownToLine size={16} />} onClick={() => setOp({ mode: 'receive', n: Date.now() })}>Receive</Button>}
            {goods && p.is_active && can('inventory.issue') && <Button variant="secondary" leftIcon={<ArrowUpFromLine size={16} />} onClick={() => setOp({ mode: 'issue', n: Date.now() })}>Issue</Button>}
            {canManage && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setDeleting(true)}>Delete</Button>}
            {canManage && <Button leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
          </>
        }
      />

      <div className={crud.detailGrid}>
        <div className={styles.stack}>
          {goods ? <StockCard product={p} /> : <Card><CardBody>Services are not stocked, so there are no quantities to show.</CardBody></Card>}
          {goods && <MovementsCard productId={p.id} />}
        </div>
        <div className={styles.stack}>
          <Card>
            <CardHeader title="Image" actions={canManage ? <ImageUpload product={p} /> : undefined} />
            <CardBody>
              {p.image_url ? (
                // eslint-disable-next-line @next/next/no-img-element -- served from the API's storage domain
                <img src={p.image_url} alt={p.name} className={styles.image} style={{ width: '100%', height: 'auto', maxHeight: 280, objectFit: 'contain' }} />
              ) : (
                <div className={styles.image} style={{ width: '100%' }}><Package size={32} aria-hidden /></div>
              )}
            </CardBody>
          </Card>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>Type</dt><dd>{humanize(p.type)}</dd>
                <dt>Category</dt><dd>{p.category?.name ?? 'Uncategorised'}</dd>
                <dt>Unit</dt><dd>{p.uom ? `${p.uom.name} (${p.uom.symbol})` : '—'}</dd>
                <dt>Cost price</dt><dd>{formatMoney(p.cost_price, currency)}</dd>
                <dt>Sale price</dt><dd>{formatMoney(p.sale_price, currency)}</dd>
                <dt>Tax rate</dt><dd>{p.tax_rate !== null ? formatPercent(p.tax_rate, { fractionDigits: 2 }) : '—'}</dd>
                {goods && <><dt>Reorder level</dt><dd>{formatQty(p.reorder_level)}</dd></>}
                {goods && <><dt>Reorder quantity</dt><dd>{formatQty(p.reorder_quantity)}</dd></>}
                {goods && <><dt>Tracking</dt><dd>{[p.track_batches && 'Batches', p.track_serials && 'Serial numbers'].filter(Boolean).join(', ') || 'None'}</dd></>}
                <dt>Description</dt><dd>{p.description || '—'}</dd>
                <dt>Updated</dt><dd>{formatDateTime(p.updated_at)}</dd>
              </dl>
            </CardBody>
          </Card>
        </div>
      </div>

      {canManage && <ProductForm key={editing ? 'open' : 'closed'} open={editing} product={p} onClose={() => setEditing(false)} />}
      {op && <StockOperationDrawer key={op.n} open mode={op.mode} defaultProduct={p} onClose={() => setOp(null)} />}
      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        title={`Delete ${p.sku}?`}
        description="Products with stock on hand cannot be deleted — adjust stock to zero or deactivate the product instead."
        destructive
        confirmLabel="Delete product"
        onConfirm={async () => {
          await del.mutateAsync(p.id).catch(() => undefined);
          setDeleting(false);
        }}
      />
    </div>
  );
}
