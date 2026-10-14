'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation } from '@tanstack/react-query';
import { Pencil, Send, Trash2 } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, ErrorState, FormField, FormGrid, PageHeader, Select, SkeletonCard, StatusBadge, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { formatDateTime, formatMoney } from '@/lib/format';
import {
  ADJUSTMENT_REASONS, adjustments, isInsufficientStock, stockApi, type AdjustmentInput, type InventoryApiError, type StockAdjustment, type StockAdjustmentReason,
} from '@/features/inventory/api';
import { LinesEditor, blankLine, collectErrors, type DraftLine } from '@/features/inventory/LinesEditor';
import { formatQty, sumDecimals, toScaled } from '@/features/inventory/decimal';
import { InsufficientStockAlert, SignedQty, useCurrency, useInvalidateStock, useWarehouseOptions } from '@/features/inventory/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

const reasonLabel = (r: string) => ADJUSTMENT_REASONS.find((x) => x.value === r)?.label ?? r;

function Editor({ adjustment, onDone }: { adjustment: StockAdjustment | null; onDone?: () => void }) {
  const router = useRouter();
  const wh = useWarehouseOptions({ activeOnly: true });
  const create = adjustments.useCreate({ onSuccess: (a) => router.replace(`/inventory/adjustments/${a.id}`) });
  const update = adjustments.useUpdate({ onSuccess: () => onDone?.() });
  const [warehouseId, setWarehouseId] = useState(adjustment ? String(adjustment.warehouse_id) : '');
  const [reason, setReason] = useState<StockAdjustmentReason>(adjustment?.reason ?? 'count');
  const [notes, setNotes] = useState(adjustment?.notes ?? '');
  const [lines, setLines] = useState<DraftLine[]>(() =>
    adjustment?.lines?.length
      ? adjustment.lines.map((l) => blankLine(l.product ?? { id: l.product_id, sku: `#${l.product_id}`, name: '' }, l.counted_quantity))
      : [blankLine()],
  );
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const effectiveWarehouse = warehouseId || (!adjustment && wh.defaultId ? String(wh.defaultId) : '');

  const save = async () => {
    setError(null);
    setFieldErrors({});
    const filled = lines.filter((l) => l.product);
    if (!effectiveWarehouse) return setError('Choose a warehouse.');
    if (filled.length === 0) return setError('Add at least one product line.');
    if (filled.some((l) => l.quantity.trim() === '')) return setError('Enter the counted quantity on every line (0 is allowed).');
    const ids = filled.map((l) => l.product!.id);
    if (new Set(ids).size !== ids.length) return setError('Each product can appear only once.');

    const body: AdjustmentInput = {
      warehouse_id: Number(effectiveWarehouse),
      reason,
      notes: notes || null,
      lines: filled.map((l) => ({ product_id: l.product!.id, counted_quantity: l.quantity })),
    };
    try {
      if (adjustment) await update.mutateAsync({ id: adjustment.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const err = e as ApiError;
      if (err.status === 422) {
        const c = collectErrors(err.errors, err.message);
        setFieldErrors(c.fieldErrors);
        setError(c.message);
      } else setError(err.message);
    }
  };

  return (
    <Card>
      <CardHeader
        title={adjustment ? `Edit draft ${adjustment.number}` : 'New stock adjustment'}
        description="Enter the quantity you actually counted. The system quantity and difference are calculated when you save."
      />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={2}>
          <FormField label="Warehouse" required error={fieldErrors.warehouse_id}>
            <Select value={effectiveWarehouse} placeholder="Select warehouse" options={wh.options} onChange={(e) => setWarehouseId(e.target.value)} />
          </FormField>
          <FormField label="Reason" required error={fieldErrors.reason}>
            <Select value={reason} options={ADJUSTMENT_REASONS} onChange={(e) => setReason(e.target.value as StockAdjustmentReason)} />
          </FormField>
          <FormField label="Notes" span="full" error={fieldErrors.notes}>
            <Textarea rows={2} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>
        </FormGrid>
        <div style={{ marginTop: 20 }}>
          <LinesEditor lines={lines} onChange={setLines} errors={fieldErrors} quantityLabel="Counted quantity" quantityField="counted_quantity" />
        </div>
        <div className={styles.row} style={{ marginTop: 16, justifyContent: 'flex-end' }}>
          <div className={styles.actions}>
            <Button variant="secondary" onClick={() => (adjustment ? onDone?.() : router.back())}>Cancel</Button>
            <Button onClick={save} loading={create.isPending || update.isPending}>Save draft</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export default function AdjustmentPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const toast = useToast();
  const currency = useCurrency();
  const invalidate = useInvalidateStock();
  const isNew = id === 'new';
  const q = adjustments.useOne(isNew ? undefined : id);
  const [confirm, setConfirm] = useState<'post' | 'delete' | null>(null);
  const [editing, setEditing] = useState(false);
  const [conflict, setConflict] = useState<InventoryApiError | null>(null);
  const canAdjust = can('inventory.adjust');

  const post = useMutation<StockAdjustment, InventoryApiError, number>({
    mutationFn: (aid) => stockApi.postAdjustment(aid),
    onSuccess: () => {
      void invalidate();
      toast.success('Adjustment posted to the stock ledger');
    },
    onError: (e) => {
      if (isInsufficientStock(e)) setConflict(e);
      else toast.error('Could not post adjustment', e.message);
    },
  });
  const del = adjustments.useDelete({ onSuccess: () => router.replace('/inventory/adjustments') });

  if (isNew) {
    if (!canAdjust) return <Alert variant="warning">You don’t have permission to adjust stock.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New stock adjustment" backHref="/inventory/adjustments" backLabel="Adjustments" />
        <Editor adjustment={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const a = q.data;
  const draft = a.status === 'draft';

  if (editing && draft) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${a.number}`} backHref="/inventory/adjustments" backLabel="Adjustments" />
        <Editor adjustment={a} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const lines = a.lines ?? [];
  const netDiff = sumDecimals(lines.map((l) => l.difference));
  const value = (diff: string, cost: string) => {
    // Exact 4dp × 4dp product → 8dp, rounded down to 4dp for display.
    const p = toScaled(diff) * toScaled(cost);
    const scaled = p / 10000n;
    const neg = scaled < 0n;
    const abs = neg ? -scaled : scaled;
    return `${neg ? '-' : ''}${abs / 10000n}.${(abs % 10000n).toString().padStart(4, '0')}`;
  };
  const netValue = sumDecimals(lines.map((l) => value(l.difference, l.unit_cost)));

  return (
    <div className={styles.stack}>
      <PageHeader
        title={a.number}
        description={`${reasonLabel(a.reason)} · ${a.warehouse?.name ?? ''}`}
        backHref="/inventory/adjustments"
        backLabel="Adjustments"
        meta={<StatusBadge status={a.status} />}
        actions={
          draft && canAdjust && (
            <>
              <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>
              <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>
              <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('post')} disabled={lines.length === 0}>Post</Button>
            </>
          )
        }
      />

      {draft && <Alert variant="info">System quantities are captured when the draft is saved and refreshed on posting. Review the differences before you post.</Alert>}
      {conflict?.details && <InsufficientStockAlert details={conflict.details} message={conflict.message} />}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 620 }}>
                <thead>
                  <tr><th>Product</th><th className={crud.r}>System qty</th><th className={crud.r}>Counted</th><th className={crud.r}>Difference</th><th className={crud.r}>Unit cost</th><th className={crud.r}>Value</th></tr>
                </thead>
                <tbody>
                  {lines.map((l) => (
                    <tr key={l.id} className={conflict?.details?.product_id === l.product_id ? styles.invalidRow : undefined}>
                      <td><span className={crud.mono}>{l.product?.sku}</span> {l.product?.name}</td>
                      <td className={crud.r}>{formatQty(l.system_quantity)}</td>
                      <td className={crud.r}>{formatQty(l.counted_quantity)}</td>
                      <td className={crud.r}><SignedQty value={l.difference} /></td>
                      <td className={crud.r}>{formatMoney(l.unit_cost, currency)}</td>
                      <td className={crud.r}>{formatMoney(value(l.difference, l.unit_cost), currency, undefined, { signDisplay: 'exceptZero' })}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot>
                  <tr>
                    <td colSpan={3}>Net change</td>
                    <td className={crud.r}><SignedQty value={netDiff} /></td>
                    <td />
                    <td className={crud.r}>{formatMoney(netValue, currency, undefined, { signDisplay: 'exceptZero' })}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Warehouse</dt><dd>{a.warehouse ? `${a.warehouse.code} · ${a.warehouse.name}` : '—'}</dd>
              <dt>Reason</dt><dd>{reasonLabel(a.reason)}</dd>
              <dt>Created</dt><dd>{formatDateTime(a.created_at)}</dd>
              <dt>Posted</dt><dd>{a.posted_at ? formatDateTime(a.posted_at) : 'Not posted'}</dd>
              <dt>Notes</dt><dd>{a.notes || '—'}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>

      <ConfirmDialog
        open={confirm === 'post'}
        onClose={() => setConfirm(null)}
        title={`Post ${a.number}?`}
        description="Posting writes the differences to the stock ledger and cannot be undone. Corrections require a new adjustment."
        destructive
        confirmLabel="Post adjustment"
        onConfirm={async () => {
          setConflict(null);
          await post.mutateAsync(a.id).catch(() => undefined);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete draft ${a.number}?`}
        destructive
        confirmLabel="Delete draft"
        onConfirm={async () => {
          await del.mutateAsync(a.id).catch(() => undefined);
          setConfirm(null);
        }}
      />
    </div>
  );
}
