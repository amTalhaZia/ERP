'use client';

import { Suspense, use, useState } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { useMutation } from '@tanstack/react-query';
import { Ban, CheckCircle2, Trash2, Undo2 } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, ErrorState, FormField, FormGrid, Input, PageHeader, Select, SkeletonCard, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import {
  goodsReceipts, procurementApi, purchaseOrders, useInvalidate, useWarehouses,
  type GoodsReceipt, type GoodsReceiptInput, type PurchaseOrder,
} from '@/features/procurement/api';
import { DocStatus } from '@/features/procurement/components';
import { ReturnModal, ReturnsPanel } from '@/features/procurement/Returns';
import { NO_ERRORS, splitServerErrors, type SplitErrors } from '@/features/procurement/errors';
import { editable } from '@/features/procurement/LinesEditor';
import { formatQty, fromUnits, sanitizeDecimal, toUnits } from '@/features/procurement/decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/procurement/procurement.module.scss';

interface Row {
  lineId: number;
  receive: string;
  rejected: string;
  reason: string;
  batch: string;
  expiry: string;
}

const todayIso = () => new Date().toISOString().slice(0, 10);

function buildRows(order: PurchaseOrder, receipt: GoodsReceipt | null): Row[] {
  const draft = new Map((receipt?.lines ?? []).map((l) => [l.purchase_order_line_id, l]));
  return (order.lines ?? [])
    .filter((l) => toUnits(l.quantity_remaining) > 0n || draft.has(l.id))
    .map((l) => {
      const d = draft.get(l.id);
      if (receipt) {
        return {
          lineId: l.id,
          receive: d ? editable(d.quantity_received) : '',
          rejected: d && toUnits(d.quantity_rejected) ? editable(d.quantity_rejected) : '',
          reason: d?.rejection_reason ?? '',
          batch: d?.batch_number ?? '',
          expiry: d?.expires_at ?? '',
        };
      }
      return { lineId: l.id, receive: editable(l.quantity_remaining), rejected: '', reason: '', batch: '', expiry: '' };
    });
}

/** Draft receipt editor (new or existing draft). Owns its header so Confirm can be gated on unsaved changes. */
function ReceiptEditor({ order, receipt }: { order: PurchaseOrder; receipt: GoodsReceipt | null }) {
  const router = useRouter();
  const toast = useToast();
  const can = useCan();
  const invalidate = useInvalidate();
  const warehouses = useWarehouses();
  const [date, setDate] = useState(receipt?.received_date ?? todayIso);
  const [warehouseId, setWarehouseId] = useState(String(receipt?.warehouse_id ?? order.warehouse_id ?? ''));
  const [note, setNote] = useState(receipt?.supplier_delivery_note ?? '');
  const [notes, setNotes] = useState(receipt?.notes ?? '');
  const [rows, setRows] = useState<Row[]>(() => buildRows(order, receipt));
  const [dirty, setDirty] = useState(!receipt);
  const [errors, setErrors] = useState<SplitErrors>(NO_ERRORS);
  const [confirm, setConfirm] = useState<'confirm' | 'cancel' | 'delete' | null>(null);

  const createReceipt = useMutation<GoodsReceipt, ApiError, GoodsReceiptInput>({
    mutationFn: (body) => procurementApi.createReceipt(order.id, body),
    onSuccess: (g) => {
      void invalidate('goods-receipts', 'purchase-orders');
      toast.success(`Draft receipt ${g.number} saved`);
      router.replace(`/procurement/receipts/${g.id}`);
    },
  });
  const update = goodsReceipts.useUpdate({ onSuccess: () => setDirty(false) });
  const confirmGr = goodsReceipts.useAction('confirm', { success: 'Goods receipt confirmed', onSuccess: () => void invalidate('purchase-orders') });
  const cancelGr = goodsReceipts.useAction('cancel', { success: 'Draft receipt cancelled' });
  const del = goodsReceipts.useDelete({ onSuccess: () => router.replace(`/procurement/orders/${order.id}`) });

  const lineById = new Map((order.lines ?? []).map((l) => [l.id, l]));
  const set = (lineId: number, patch: Partial<Row>) => {
    setDirty(true);
    setRows((rs) => rs.map((r) => (r.lineId === lineId ? { ...r, ...patch } : r)));
  };
  const header = (fn: () => void) => {
    setDirty(true);
    fn();
  };

  const save = async () => {
    setErrors(NO_ERRORS);
    const chosen = rows.filter((r) => toUnits(r.receive) > 0n || toUnits(r.rejected) > 0n);
    if (chosen.length === 0) {
      setErrors({ ...NO_ERRORS, message: 'Enter a received quantity on at least one line.' });
      return;
    }
    const body: GoodsReceiptInput = {
      received_date: date,
      warehouse_id: warehouseId ? Number(warehouseId) : null,
      supplier_delivery_note: note.trim() || null,
      notes: notes.trim() || null,
      lines: chosen.map((r) => ({
        purchase_order_line_id: r.lineId,
        quantity_received: r.receive || '0',
        quantity_rejected: r.rejected || '0',
        rejection_reason: r.reason.trim() || null,
        batch_number: r.batch.trim() || null,
        expires_at: r.expiry || null,
      })),
    };
    try {
      if (receipt) await update.mutateAsync({ id: receipt.id, body });
      else await createReceipt.mutateAsync(body);
    } catch (e) {
      setErrors(splitServerErrors(e as ApiError, chosen.map((r) => r.lineId)));
    }
  };

  const canManage = can('goods_receipts.manage');
  const over = rows.some((r) => {
    const l = lineById.get(r.lineId);
    return l ? toUnits(r.receive) > toUnits(l.quantity_remaining) : false;
  });

  return (
    <div className={styles.stack}>
      <PageHeader
        title={receipt ? receipt.number : 'Receive goods'}
        description={<>Against <Link href={`/procurement/orders/${order.id}`}>{order.number}</Link>{order.supplier ? ` · ${order.supplier.name}` : ''}</>}
        backHref={receipt ? '/procurement/receipts' : `/procurement/orders/${order.id}`}
        backLabel={receipt ? 'Goods receipts' : order.number}
        meta={receipt ? <DocStatus status={receipt.status} /> : undefined}
        actions={
          canManage && (
            <>
              {receipt && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
              {receipt && <Button variant="ghost" leftIcon={<Ban size={16} />} onClick={() => setConfirm('cancel')}>Cancel draft</Button>}
              <Button variant={receipt ? 'secondary' : 'primary'} onClick={save} loading={createReceipt.isPending || update.isPending} disabled={over}>
                {receipt ? 'Save changes' : 'Save draft'}
              </Button>
              {receipt && (
                <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('confirm')} disabled={dirty} title={dirty ? 'Save your changes first' : undefined}>
                  Confirm receipt
                </Button>
              )}
            </>
          )
        }
      />

      {errors.message && <Alert variant="danger">{errors.message}</Alert>}
      {receipt && dirty && <Alert variant="info">You have unsaved changes. Save them before confirming the receipt.</Alert>}
      {!receipt && <Alert variant="info">Quantities default to what is still outstanding. Save as a draft, review, then confirm to update stock.</Alert>}

      <Card>
        <CardBody>
          <FormGrid columns={4}>
            <FormField label="Received on" required error={errors.header.received_date}>
              <DateInput value={date} onValueChange={(v) => header(() => setDate(v ?? ''))} />
            </FormField>
            <FormField label="Warehouse" error={errors.header.warehouse_id}>
              <Select value={warehouseId} onChange={(e) => header(() => setWarehouseId(e.target.value))} options={[{ value: '', label: 'Not set' }, ...(warehouses.data ?? []).map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` }))]} />
            </FormField>
            <FormField label="Supplier delivery note" error={errors.header.supplier_delivery_note}>
              <Input maxLength={100} value={note} onChange={(e) => header(() => setNote(e.target.value))} />
            </FormField>
            <FormField label="Notes" error={errors.header.notes}>
              <Input maxLength={2000} value={notes} onChange={(e) => header(() => setNotes(e.target.value))} />
            </FormField>
          </FormGrid>
        </CardBody>
      </Card>

      <Card>
        <CardHeader title="Lines" description="Receive now = accepted quantity. Record rejected units separately with a reason." />
        <CardBody padding="none">
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 1180 }}>
              <thead>
                <tr>
                  <th>Item</th>
                  <th className={crud.r}>Ordered</th>
                  <th className={crud.r}>Prev. received</th>
                  <th className={crud.r}>Remaining</th>
                  <th className={crud.r} style={{ width: 120 }}>Receive now</th>
                  <th className={crud.r} style={{ width: 110 }}>Rejected</th>
                  <th style={{ width: 170 }}>Rejection reason</th>
                  <th style={{ width: 130 }}>Batch</th>
                  <th style={{ width: 160 }}>Expiry</th>
                </tr>
              </thead>
              <tbody>
                {rows.length === 0 && (
                  <tr><td colSpan={9} className={crud.muted}>Every line on this order has been fully received.</td></tr>
                )}
                {rows.map((r) => {
                  const l = lineById.get(r.lineId);
                  if (!l) return null;
                  const err = errors.rows[r.lineId] ?? {};
                  const overLine = toUnits(r.receive) > toUnits(l.quantity_remaining);
                  const recvErr = err.quantity_received ?? (overLine ? `Only ${formatQty(l.quantity_remaining)} remaining` : undefined);
                  const rowErr = recvErr || Object.keys(err).length > 0;
                  return (
                    <tr key={r.lineId} className={rowErr ? styles.rowInvalid : undefined}>
                      <td>{l.description}{l.uom && <span className={styles.linked}>{l.uom}</span>}{err.purchase_order_line_id && <span className={styles.cellError}>{err.purchase_order_line_id}</span>}</td>
                      <td className={crud.r}>{formatQty(l.quantity)}</td>
                      <td className={crud.r}>{formatQty(l.quantity_received)}</td>
                      <td className={crud.r}>{formatQty(l.quantity_remaining)}</td>
                      <td>
                        <Input aria-label={`Receive now — ${l.description}`} inputMode="decimal" style={{ textAlign: 'right' }} value={r.receive} invalid={!!recvErr} onChange={(e) => set(r.lineId, { receive: sanitizeDecimal(e.target.value) })} />
                        {recvErr && <span className={styles.cellError}>{recvErr}</span>}
                      </td>
                      <td>
                        <Input aria-label={`Rejected — ${l.description}`} inputMode="decimal" style={{ textAlign: 'right' }} value={r.rejected} invalid={!!err.quantity_rejected} onChange={(e) => set(r.lineId, { rejected: sanitizeDecimal(e.target.value) })} />
                        {err.quantity_rejected && <span className={styles.cellError}>{err.quantity_rejected}</span>}
                      </td>
                      <td>
                        <Input aria-label={`Rejection reason — ${l.description}`} maxLength={255} value={r.reason} disabled={!toUnits(r.rejected)} invalid={!!err.rejection_reason} onChange={(e) => set(r.lineId, { reason: e.target.value })} />
                        {err.rejection_reason && <span className={styles.cellError}>{err.rejection_reason}</span>}
                      </td>
                      <td>
                        <Input aria-label={`Batch — ${l.description}`} maxLength={60} value={r.batch} invalid={!!err.batch_number} onChange={(e) => set(r.lineId, { batch: e.target.value })} />
                        {err.batch_number && <span className={styles.cellError}>{err.batch_number}</span>}
                      </td>
                      <td>
                        <DateInput aria-label={`Expiry — ${l.description}`} value={r.expiry} invalid={!!err.expires_at} onValueChange={(v) => set(r.lineId, { expiry: v ?? '' })} />
                        {err.expires_at && <span className={styles.cellError}>{err.expires_at}</span>}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </CardBody>
      </Card>

      {receipt && (
        <>
          <ConfirmDialog
            open={confirm === 'confirm'}
            onClose={() => setConfirm(null)}
            title={`Confirm ${receipt.number}?`}
            description="This is irreversible. Received quantities are posted to the purchase order and stock is added to the warehouse. Corrections afterwards require a purchase return."
            confirmLabel="Confirm receipt"
            onConfirm={async () => {
              try {
                await confirmGr.mutateAsync({ id: receipt.id });
              } catch (e) {
                setErrors(splitServerErrors(e as ApiError, (receipt.lines ?? []).map((l) => l.purchase_order_line_id)));
              } finally {
                setConfirm(null);
              }
            }}
          />
          <ConfirmDialog
            open={confirm === 'cancel'}
            onClose={() => setConfirm(null)}
            title={`Cancel ${receipt.number}?`}
            description="The draft is kept for reference but can no longer be confirmed."
            destructive
            confirmLabel="Cancel draft"
            cancelLabel="Keep"
            onConfirm={async () => {
              try {
                await cancelGr.mutateAsync({ id: receipt.id });
              } finally {
                setConfirm(null);
              }
            }}
          />
          <ConfirmDialog
            open={confirm === 'delete'}
            onClose={() => setConfirm(null)}
            title={`Delete draft ${receipt.number}?`}
            destructive
            confirmLabel="Delete draft"
            onConfirm={async () => {
              try {
                await del.mutateAsync(receipt.id);
              } finally {
                setConfirm(null);
              }
            }}
          />
        </>
      )}
    </div>
  );
}

function ReceiptView({ receipt, order }: { receipt: GoodsReceipt; order: PurchaseOrder | undefined }) {
  const can = useCan();
  const warehouses = useWarehouses();
  const [returning, setReturning] = useState(false);
  const currency = order?.currency ?? 'USD';
  const warehouse = warehouses.data?.find((w) => w.id === receipt.warehouse_id);
  const canReturn =
    receipt.status === 'confirmed' &&
    can('goods_receipts.manage') &&
    !!order?.lines?.some((l) => toUnits(l.quantity_received) - toUnits(l.quantity_returned) > 0n);

  return (
    <div className={styles.stack}>
      <PageHeader
        title={receipt.number}
        description={receipt.purchase_order ? <>Against <Link href={`/procurement/orders/${receipt.purchase_order.id}`}>{receipt.purchase_order.number}</Link>{order?.supplier ? ` · ${order.supplier.name}` : ''}</> : undefined}
        backHref="/procurement/receipts"
        backLabel="Goods receipts"
        meta={<DocStatus status={receipt.status} />}
        actions={canReturn && <Button variant="secondary" leftIcon={<Undo2 size={16} />} onClick={() => setReturning(true)}>Return goods</Button>}
      />
      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 820 }}>
                <thead>
                  <tr><th>Item</th><th className={crud.r}>Ordered</th><th className={crud.r}>Received</th><th className={crud.r}>Rejected</th><th>Reason</th><th>Batch</th><th>Expiry</th><th className={crud.r}>Unit cost</th><th className={crud.r}>Value</th></tr>
                </thead>
                <tbody>
                  {receipt.lines?.map((l) => (
                    <tr key={l.id}>
                      <td>{l.description}</td>
                      <td className={crud.r}>{formatQty(l.quantity_ordered)}</td>
                      <td className={crud.r}>{formatQty(l.quantity_received)}</td>
                      <td className={crud.r}>{toUnits(l.quantity_rejected) ? formatQty(l.quantity_rejected) : '—'}</td>
                      <td>{l.rejection_reason ?? '—'}</td>
                      <td>{l.batch_number ?? '—'}</td>
                      <td>{formatDate(l.expires_at)}</td>
                      <td className={crud.r}>{formatMoney(l.unit_cost, currency)}</td>
                      <td className={crud.r}>{formatMoney(fromUnits((toUnits(l.quantity_received) * toUnits(l.unit_cost)) / 10000n), currency)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>
        <Card>
          <CardHeader title="Details" />
          <CardBody>
            <dl className={crud.dl}>
              <dt>Received on</dt><dd>{formatDate(receipt.received_date)}</dd>
              <dt>Received by</dt><dd>{receipt.receiver?.name ?? '—'}</dd>
              <dt>Warehouse</dt><dd>{warehouse ? `${warehouse.name} (${warehouse.code})` : receipt.warehouse_id ? `#${receipt.warehouse_id}` : '—'}</dd>
              <dt>Delivery note</dt><dd>{receipt.supplier_delivery_note ?? '—'}</dd>
              <dt>Confirmed</dt><dd>{receipt.confirmed_at ? formatDateTime(receipt.confirmed_at) : '—'}</dd>
              {receipt.cancelled_at && (<><dt>Cancelled</dt><dd>{formatDateTime(receipt.cancelled_at)}</dd></>)}
              {receipt.notes && (<><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{receipt.notes}</dd></>)}
            </dl>
          </CardBody>
        </Card>
      </div>
      {receipt.status === 'confirmed' && (
        <Card>
          <CardHeader title="Returns from this receipt" />
          <CardBody><ReturnsPanel orderId={receipt.purchase_order_id} goodsReceiptId={receipt.id} currency={currency} /></CardBody>
        </Card>
      )}
      {returning && order && <ReturnModal order={order} goodsReceiptId={receipt.id} open onClose={() => setReturning(false)} />}
    </div>
  );
}

function NewReceipt() {
  const params = useSearchParams();
  const can = useCan();
  const po = params.get('po') ?? undefined;
  const q = purchaseOrders.useOne(po);
  if (!can('goods_receipts.manage')) return <Alert variant="warning">You don’t have permission to receive goods.</Alert>;
  if (!po) return <Alert variant="warning">Open an approved purchase order and choose “Receive goods”. <Link href="/procurement/orders?f.status=approved">Approved orders →</Link></Alert>;
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  if (!['approved', 'partially_received'].includes(q.data.status)) {
    return <Alert variant="warning">{q.data.number} is {q.data.status.replace(/_/g, ' ')} — goods can only be received against approved orders. <Link href={`/procurement/orders/${q.data.id}`}>Back to the order</Link></Alert>;
  }
  return <ReceiptEditor key={q.data.id} order={q.data} receipt={null} />;
}

function ExistingReceipt({ id }: { id: string }) {
  const can = useCan();
  const q = goodsReceipts.useOne(id);
  const order = purchaseOrders.useOne(q.data?.purchase_order_id, { enabled: !!q.data });
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const g = q.data;
  if (g.status === 'draft' && can('goods_receipts.manage')) {
    if (order.error) return <ErrorState error={order.error} onRetry={() => void order.refetch()} />;
    if (!order.data) return <SkeletonCard />;
    return <ReceiptEditor key={`${g.id}-${g.updated_at ?? ''}`} order={order.data} receipt={g} />;
  }
  return <ReceiptView receipt={g} order={order.data} />;
}

export default function GoodsReceiptPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return <Suspense fallback={<SkeletonCard />}>{id === 'new' ? <NewReceipt /> : <ExistingReceipt id={id} />}</Suspense>;
}
