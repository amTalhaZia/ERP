'use client';

import { useState } from 'react';
import { CheckCircle2, Ban, Trash2, Undo2 } from 'lucide-react';
import {
  Alert, Button, ConfirmDialog, DateInput, EmptyState, ErrorState, FormField, FormGrid, IconButton, Input, Modal, Select, SkeletonTable, Textarea,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatMoney, humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { RETURN_REASONS, purchaseReturns, useInvalidate, type PurchaseOrder, type PurchaseReturn, type PurchaseReturnReason } from './api';
import { DocStatus } from './components';
import { formatQty, fromUnits, mul, sanitizeDecimal, toUnits } from './decimal';
import { NO_ERRORS, splitServerErrors, type SplitErrors } from './errors';
import crud from '@/components/crud/crud.module.scss';
import styles from './procurement.module.scss';

const today = () => new Date().toISOString().slice(0, 10);

/** Effective unit cost after line discount — what the server uses for returns. */
const effectiveCost = (unitPrice: string, discount: string) => {
  const factor = 10000n - toUnits(discount) / 100n; // 1 − disc/100, at 4 dp
  return mul(toUnits(unitPrice), factor);
};

/**
 * Create a draft purchase return against an order (optionally a specific goods
 * receipt). Each line may return at most received − already returned.
 */
export function ReturnModal({ order, goodsReceiptId, open, onClose }: { order: PurchaseOrder; goodsReceiptId?: number | null; open: boolean; onClose: () => void }) {
  const returnable = (order.lines ?? []).filter((l) => toUnits(l.quantity_received) - toUnits(l.quantity_returned) > 0n);
  const [qty, setQty] = useState<Record<number, string>>({});
  const [reason, setReason] = useState<PurchaseReturnReason>('damaged');
  const [date, setDate] = useState(today);
  const [notes, setNotes] = useState('');
  const [errors, setErrors] = useState<SplitErrors>(NO_ERRORS);
  const invalidate = useInvalidate();
  const create = purchaseReturns.useCreate({
    onSuccess: () => {
      void invalidate('purchase-orders', 'goods-receipts');
      onClose();
    },
  });

  const chosen = returnable.filter((l) => toUnits(qty[l.id]) > 0n);
  const total = chosen.reduce((a, l) => a + mul(toUnits(qty[l.id]), effectiveCost(l.unit_price, l.discount_percent)), 0n);
  const overLimit = chosen.some((l) => toUnits(qty[l.id]) > toUnits(l.quantity_received) - toUnits(l.quantity_returned));

  const submit = async () => {
    setErrors(NO_ERRORS);
    if (chosen.length === 0) {
      setErrors({ ...NO_ERRORS, message: 'Enter a quantity to return on at least one line.' });
      return;
    }
    try {
      await create.mutateAsync({
        purchase_order_id: order.id,
        goods_receipt_id: goodsReceiptId ?? null,
        return_date: date,
        reason,
        notes: notes.trim() || null,
        lines: chosen.map((l) => ({ purchase_order_line_id: l.id, quantity: qty[l.id]! })),
      });
    } catch (e) {
      setErrors(splitServerErrors(e as ApiError, chosen.map((l) => l.id)));
    }
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      size="lg"
      title={`Return goods to supplier — ${order.number}`}
      description="Creates a draft return. Confirm it from the Returns tab to update received quantities and stock."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button leftIcon={<Undo2 size={16} />} onClick={submit} loading={create.isPending} disabled={returnable.length === 0 || overLimit}>Create return</Button>
        </>
      }
    >
      <div className={styles.stack}>
        {errors.message && <Alert variant="danger">{errors.message}</Alert>}
        {returnable.length === 0 ? (
          <EmptyState size="sm" title="Nothing to return" description="Only received quantities that have not been returned can be sent back." />
        ) : (
          <>
            <FormGrid columns={2}>
              <FormField label="Reason" required error={errors.header.reason}>
                <Select value={reason} onChange={(e) => setReason(e.target.value as PurchaseReturnReason)} options={RETURN_REASONS} />
              </FormField>
              <FormField label="Return date" required error={errors.header.return_date}>
                <DateInput value={date} onValueChange={(v) => setDate(v ?? '')} />
              </FormField>
            </FormGrid>
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 560 }}>
                <thead>
                  <tr><th>Item</th><th className={crud.r}>Received</th><th className={crud.r}>Returned</th><th className={crud.r}>Returnable</th><th className={crud.r} style={{ width: 130 }}>Return qty</th></tr>
                </thead>
                <tbody>
                  {returnable.map((l) => {
                    const max = toUnits(l.quantity_received) - toUnits(l.quantity_returned);
                    const err = errors.rows[l.id]?.quantity ?? (toUnits(qty[l.id]) > max ? `At most ${formatQty(fromUnits(max))}` : undefined);
                    return (
                      <tr key={l.id} className={err ? styles.rowInvalid : undefined}>
                        <td>{l.description}</td>
                        <td className={crud.r}>{formatQty(l.quantity_received)}</td>
                        <td className={crud.r}>{formatQty(l.quantity_returned)}</td>
                        <td className={crud.r}>{formatQty(fromUnits(max))}</td>
                        <td>
                          <Input
                            aria-label={`Return quantity for ${l.description}`}
                            inputMode="decimal"
                            style={{ textAlign: 'right' }}
                            value={qty[l.id] ?? ''}
                            invalid={!!err}
                            onChange={(e) => setQty((q) => ({ ...q, [l.id]: sanitizeDecimal(e.target.value) }))}
                          />
                          {err && <span className={styles.cellError}>{err}</span>}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div className={crud.totals}>
              <span className={crud.grand}>Estimated credit</span>
              <span className={crud.grand}>{formatMoney(fromUnits(total), order.currency)}</span>
            </div>
            <FormField label="Notes" error={errors.header.notes}>
              <Textarea rows={2} maxLength={2000} value={notes} onChange={(e) => setNotes(e.target.value)} />
            </FormField>
          </>
        )}
      </div>
    </Modal>
  );
}

function ReturnDetail({ id, currency, onClose }: { id: number; currency: string; onClose: () => void }) {
  const q = purchaseReturns.useOne(id);
  const r = q.data;
  return (
    <Modal open onClose={onClose} size="lg" title={r ? `Return ${r.number}` : 'Purchase return'} description={r ? `${humanize(r.reason)} · ${formatDate(r.return_date)}` : undefined}>
      {q.error ? (
        <ErrorState error={q.error} onRetry={() => void q.refetch()} />
      ) : !r ? (
        <SkeletonTable rows={3} cols={4} />
      ) : (
        <div className={styles.stack}>
          <div className={styles.row}><DocStatus status={r.status} />{r.goods_receipt && <span className={crud.muted}>Against receipt {r.goods_receipt.number}</span>}</div>
          <div className={crud.tableScroll}>
            <table className={crud.lines}>
              <thead><tr><th>Item</th><th className={crud.r}>Qty</th><th className={crud.r}>Unit cost</th><th className={crud.r}>Total</th></tr></thead>
              <tbody>
                {r.lines?.map((l) => (
                  <tr key={l.id}><td>{l.description}</td><td className={crud.r}>{formatQty(l.quantity)}</td><td className={crud.r}>{formatMoney(l.unit_cost, currency)}</td><td className={crud.r}>{formatMoney(l.line_total, currency)}</td></tr>
                ))}
              </tbody>
              <tfoot><tr><td colSpan={3}>Total</td><td className={crud.r}>{formatMoney(r.total, currency)}</td></tr></tfoot>
            </table>
          </div>
          {r.notes && <p style={{ whiteSpace: 'pre-wrap', margin: 0 }}>{r.notes}</p>}
        </div>
      )}
    </Modal>
  );
}

/** Returns for an order (and optionally one receipt) with confirm / cancel / delete on drafts. */
export function ReturnsPanel({ orderId, goodsReceiptId, currency }: { orderId: number; goodsReceiptId?: number; currency: string }) {
  const can = useCan();
  const params: Record<string, unknown> = { 'filter[purchase_order_id]': orderId, per_page: 50, sort: '-return_date' };
  if (goodsReceiptId) params['filter[goods_receipt_id]'] = goodsReceiptId;
  const q = purchaseReturns.useList(params);
  const confirmRet = purchaseReturns.useAction('confirm', { success: 'Return confirmed' });
  const cancelRet = purchaseReturns.useAction('cancel', { success: 'Return cancelled' });
  const del = purchaseReturns.useDelete();
  const invalidate = useInvalidate();
  const [viewing, setViewing] = useState<number | null>(null);
  const [acting, setActing] = useState<{ kind: 'confirm' | 'cancel' | 'delete'; ret: PurchaseReturn } | null>(null);
  const canManage = can('goods_receipts.manage');

  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} size="sm" />;
  if (q.isLoading) return <SkeletonTable rows={2} cols={5} />;
  const rows = q.data?.data ?? [];
  if (rows.length === 0) return <EmptyState size="sm" title="No returns" description="Goods sent back to the supplier will appear here." />;

  return (
    <>
      <div className={crud.tableScroll}>
        <table className={crud.lines} style={{ minWidth: 600 }}>
          <thead><tr><th>Number</th><th>Date</th><th>Reason</th><th>Receipt</th><th className={crud.r}>Total</th><th>Status</th>{canManage && <th aria-label="Actions" />}</tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.id}>
                <td><button type="button" className={crud.linkBtn} onClick={() => setViewing(r.id)}><span className={crud.mono}>{r.number}</span></button></td>
                <td>{formatDate(r.return_date)}</td>
                <td>{humanize(r.reason)}</td>
                <td>{r.goods_receipt?.number ?? '—'}</td>
                <td className={crud.r}>{formatMoney(r.total, currency)}</td>
                <td><DocStatus status={r.status} /></td>
                {canManage && (
                  <td className={crud.r} style={{ whiteSpace: 'nowrap' }}>
                    {r.status === 'draft' && (
                      <>
                        <IconButton size="sm" icon={<CheckCircle2 size={14} />} aria-label={`Confirm ${r.number}`} onClick={() => setActing({ kind: 'confirm', ret: r })} />
                        <IconButton size="sm" icon={<Ban size={14} />} aria-label={`Cancel ${r.number}`} onClick={() => setActing({ kind: 'cancel', ret: r })} />
                        <IconButton size="sm" icon={<Trash2 size={14} />} aria-label={`Delete ${r.number}`} onClick={() => setActing({ kind: 'delete', ret: r })} />
                      </>
                    )}
                  </td>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {viewing !== null && <ReturnDetail id={viewing} currency={currency} onClose={() => setViewing(null)} />}
      <ConfirmDialog
        open={acting !== null}
        onClose={() => setActing(null)}
        title={acting ? `${humanize(acting.kind)} ${acting.ret.number}?` : ''}
        description={
          acting?.kind === 'confirm'
            ? 'Confirming is irreversible: returned quantities are deducted from the order and stock leaves the warehouse.'
            : acting?.kind === 'cancel'
              ? 'The draft return will be cancelled.'
              : 'The draft return will be deleted.'
        }
        destructive={acting?.kind !== 'confirm'}
        confirmLabel={acting ? `${humanize(acting.kind)} return` : 'Confirm'}
        onConfirm={async () => {
          if (!acting) return;
          try {
            if (acting.kind === 'confirm') await confirmRet.mutateAsync({ id: acting.ret.id });
            else if (acting.kind === 'cancel') await cancelRet.mutateAsync({ id: acting.ret.id });
            else await del.mutateAsync(acting.ret.id);
            void invalidate('purchase-orders', 'goods-receipts');
          } finally {
            setActing(null);
          }
        }}
      />
    </>
  );
}
