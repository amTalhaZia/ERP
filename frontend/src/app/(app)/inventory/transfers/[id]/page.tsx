'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import { useMutation } from '@tanstack/react-query';
import { ArrowRight, Ban, PackageCheck, Pencil, Trash2, Truck } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, ErrorState, FormField, FormGrid, PageHeader, Select, SkeletonCard, StatusBadge, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import type { ApiError } from '@/lib/api/errors';
import { formatDateTime, formatMoney } from '@/lib/format';
import { isInsufficientStock, stockApi, transfers, type InventoryApiError, type StockTransfer, type TransferInput } from '@/features/inventory/api';
import { LinesEditor, blankLine, collectErrors, type DraftLine } from '@/features/inventory/LinesEditor';
import { formatQty, isPositive } from '@/features/inventory/decimal';
import { InsufficientStockAlert, useCurrency, useInvalidateStock, useWarehouseOptions } from '@/features/inventory/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/inventory/inventory.module.scss';

function Editor({ transfer, onDone }: { transfer: StockTransfer | null; onDone?: () => void }) {
  const router = useRouter();
  const wh = useWarehouseOptions({ activeOnly: true });
  const create = transfers.useCreate({ onSuccess: (t) => router.replace(`/inventory/transfers/${t.id}`) });
  const update = transfers.useUpdate({ onSuccess: () => onDone?.() });
  const [from, setFrom] = useState(transfer ? String(transfer.from_warehouse_id) : '');
  const [to, setTo] = useState(transfer ? String(transfer.to_warehouse_id) : '');
  const [notes, setNotes] = useState(transfer?.notes ?? '');
  const [lines, setLines] = useState<DraftLine[]>(() =>
    transfer?.lines?.length ? transfer.lines.map((l) => blankLine(l.product ?? { id: l.product_id, sku: `#${l.product_id}`, name: '' }, l.quantity)) : [blankLine()],
  );
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const effectiveFrom = from || (!transfer && wh.defaultId ? String(wh.defaultId) : '');

  const save = async () => {
    setError(null);
    setFieldErrors({});
    const filled = lines.filter((l) => l.product);
    if (!effectiveFrom || !to) return setError('Choose both the source and destination warehouse.');
    if (effectiveFrom === to) return setError('The destination must differ from the source warehouse.');
    if (filled.length === 0) return setError('Add at least one product line.');
    if (filled.some((l) => !isPositive(l.quantity))) return setError('Every line needs a quantity greater than zero.');
    const ids = filled.map((l) => l.product!.id);
    if (new Set(ids).size !== ids.length) return setError('Each product can appear only once.');

    const body: TransferInput = {
      from_warehouse_id: Number(effectiveFrom),
      to_warehouse_id: Number(to),
      notes: notes || null,
      lines: filled.map((l) => ({ product_id: l.product!.id, quantity: l.quantity })),
    };
    try {
      if (transfer) await update.mutateAsync({ id: transfer.id, body });
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
      <CardHeader title={transfer ? `Edit draft ${transfer.number}` : 'New stock transfer'} description="Stock leaves the source when shipped and arrives at the destination when received." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={2}>
          <FormField label="From warehouse" required error={fieldErrors.from_warehouse_id}>
            <Select value={effectiveFrom} placeholder="Select source" options={wh.options} onChange={(e) => setFrom(e.target.value)} />
          </FormField>
          <FormField label="To warehouse" required error={fieldErrors.to_warehouse_id}>
            <Select value={to} placeholder="Select destination" options={wh.options.filter((o) => o.value !== effectiveFrom)} onChange={(e) => setTo(e.target.value)} />
          </FormField>
          <FormField label="Notes" span="full" error={fieldErrors.notes}>
            <Textarea rows={2} maxLength={1000} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </FormField>
        </FormGrid>
        <div style={{ marginTop: 20 }}>
          <LinesEditor lines={lines} onChange={setLines} errors={fieldErrors} />
        </div>
        <div className={styles.row} style={{ marginTop: 16, justifyContent: 'flex-end' }}>
          <div className={styles.actions}>
            <Button variant="secondary" onClick={() => (transfer ? onDone?.() : router.back())}>Cancel</Button>
            <Button onClick={save} loading={create.isPending || update.isPending}>Save draft</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

export default function TransferPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const toast = useToast();
  const currency = useCurrency();
  const invalidate = useInvalidateStock();
  const isNew = id === 'new';
  const q = transfers.useOne(isNew ? undefined : id);
  const [confirm, setConfirm] = useState<'ship' | 'receive' | 'cancel' | 'delete' | null>(null);
  const [editing, setEditing] = useState(false);
  const [conflict, setConflict] = useState<InventoryApiError | null>(null);

  const ship = useMutation<StockTransfer, InventoryApiError, number>({
    mutationFn: (tid) => stockApi.shipTransfer(tid),
    onSuccess: () => {
      void invalidate();
      toast.success('Transfer shipped — stock is in transit');
    },
    onError: (e) => {
      if (isInsufficientStock(e)) setConflict(e);
      else toast.error('Could not ship transfer', e.message);
    },
  });
  const receive = transfers.useAction('receive', { success: 'Transfer received into destination', onSuccess: () => void invalidate() });
  const cancel = transfers.useAction('cancel', { success: 'Transfer cancelled' });
  const del = transfers.useDelete({ onSuccess: () => router.replace('/inventory/transfers') });
  const canTransfer = can('inventory.transfer');

  if (isNew) {
    if (!canTransfer) return <Alert variant="warning">You don’t have permission to create stock transfers.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New stock transfer" backHref="/inventory/transfers" backLabel="Transfers" />
        <Editor transfer={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const t = q.data;
  const draft = t.status === 'draft';

  if (editing && draft) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${t.number}`} backHref="/inventory/transfers" backLabel="Transfers" />
        <Editor transfer={t} onDone={() => setEditing(false)} />
      </div>
    );
  }

  return (
    <div className={styles.stack}>
      <PageHeader
        title={t.number}
        description={
          <span className={styles.inline}>
            {t.from_warehouse?.name ?? '—'} <ArrowRight size={14} aria-label="to" /> {t.to_warehouse?.name ?? '—'}
          </span>
        }
        backHref="/inventory/transfers"
        backLabel="Transfers"
        meta={<StatusBadge status={t.status} variant={t.status === 'in_transit' ? 'warning' : undefined} />}
        actions={
          canTransfer && (
            <>
              {draft && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
              {draft && <Button variant="secondary" leftIcon={<Ban size={16} />} onClick={() => setConfirm('cancel')}>Cancel transfer</Button>}
              {draft && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
              {draft && <Button leftIcon={<Truck size={16} />} onClick={() => setConfirm('ship')}>Ship</Button>}
              {t.status === 'in_transit' && <Button leftIcon={<PackageCheck size={16} />} onClick={() => setConfirm('receive')}>Receive</Button>}
            </>
          )
        }
      />

      {conflict?.details && <InsufficientStockAlert details={conflict.details} message={conflict.message} />}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" />
          <CardBody padding="none">
            <div className={crud.tableScroll}>
              <table className={crud.lines} style={{ minWidth: 480 }}>
                <thead><tr><th>Product</th><th className={crud.r}>Quantity</th><th className={crud.r}>Unit cost</th></tr></thead>
                <tbody>
                  {t.lines?.map((l) => (
                    <tr key={l.id} className={conflict?.details?.product_id === l.product_id ? styles.invalidRow : undefined}>
                      <td><span className={crud.mono}>{l.product?.sku}</span> {l.product?.name}</td>
                      <td className={crud.r}>{formatQty(l.quantity)}</td>
                      <td className={crud.r}>{draft ? <span className={crud.muted}>Set on shipping</span> : formatMoney(l.unit_cost, currency)}</td>
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
              <dt>From</dt><dd>{t.from_warehouse ? `${t.from_warehouse.code} · ${t.from_warehouse.name}` : '—'}</dd>
              <dt>To</dt><dd>{t.to_warehouse ? `${t.to_warehouse.code} · ${t.to_warehouse.name}` : '—'}</dd>
              <dt>Created</dt><dd>{formatDateTime(t.created_at)}</dd>
              <dt>Shipped</dt><dd>{t.shipped_at ? formatDateTime(t.shipped_at) : 'Not shipped'}</dd>
              <dt>Received</dt><dd>{t.received_at ? formatDateTime(t.received_at) : 'Not received'}</dd>
              <dt>Notes</dt><dd>{t.notes || '—'}</dd>
            </dl>
          </CardBody>
        </Card>
      </div>

      <ConfirmDialog
        open={confirm === 'ship'}
        onClose={() => setConfirm(null)}
        title={`Ship ${t.number}?`}
        description={`Stock will be deducted from ${t.from_warehouse?.name ?? 'the source warehouse'} and held in transit. The transfer can no longer be edited.`}
        confirmLabel="Ship transfer"
        onConfirm={async () => {
          setConflict(null);
          await ship.mutateAsync(t.id).catch(() => undefined);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === 'receive'}
        onClose={() => setConfirm(null)}
        title={`Receive ${t.number}?`}
        description={`Stock will be added to ${t.to_warehouse?.name ?? 'the destination warehouse'}.`}
        confirmLabel="Receive transfer"
        onConfirm={async () => {
          await receive.mutateAsync({ id: t.id }).catch(() => undefined);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === 'cancel'}
        onClose={() => setConfirm(null)}
        title={`Cancel ${t.number}?`}
        description="The draft will be marked cancelled. No stock has moved."
        destructive
        confirmLabel="Cancel transfer"
        cancelLabel="Keep"
        onConfirm={async () => {
          await cancel.mutateAsync({ id: t.id }).catch(() => undefined);
          setConfirm(null);
        }}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete draft ${t.number}?`}
        destructive
        confirmLabel="Delete draft"
        onConfirm={async () => {
          await del.mutateAsync(t.id).catch(() => undefined);
          setConfirm(null);
        }}
      />
    </div>
  );
}
