'use client';

import { use, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Ban, CheckCircle2, Lock, Mail, PackagePlus, Pencil, Send, Trash2, Undo2 } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, EmptyState, ErrorState, FormField, FormGrid, Input, PageHeader, Select, SkeletonCard,
  SkeletonTable, Tab, TabList, TabPanel, Tabs, Textarea,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectBranches } from '@/store/sessionSlice';
import {
  goodsReceipts, purchaseOrders, suppliers, useWarehouses,
  type PurchaseOrder, type PurchaseOrderInput, type SupplierOption,
} from '@/features/procurement/api';
import { LinesEditor, collectLines, editable, newLine, type DraftLine } from '@/features/procurement/LinesEditor';
import { DocStatus, ReasonDialog, Timeline, type TimelineStep } from '@/features/procurement/components';
import { ReturnModal, ReturnsPanel } from '@/features/procurement/Returns';
import { NO_ERRORS, splitServerErrors, type SplitErrors } from '@/features/procurement/errors';
import { formatQty, toUnits } from '@/features/procurement/decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/procurement/procurement.module.scss';

const todayIso = () => new Date().toISOString().slice(0, 10);

function Editor({ order, onDone }: { order: PurchaseOrder | null; onDone?: () => void }) {
  const router = useRouter();
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const branches = useAppSelector(selectBranches);
  const supplierList = suppliers.useAll<SupplierOption>();
  const warehouses = useWarehouses();
  const create = purchaseOrders.useCreate({ onSuccess: (o) => router.replace(`/procurement/orders/${o.id}`) });
  const update = purchaseOrders.useUpdate({ onSuccess: () => onDone?.() });
  const [supplierId, setSupplierId] = useState(order ? String(order.supplier_id) : '');
  const [orderDate, setOrderDate] = useState(order?.order_date ?? todayIso);
  const [expected, setExpected] = useState(order?.expected_date ?? '');
  const [warehouseId, setWarehouseId] = useState(order?.warehouse_id ? String(order.warehouse_id) : '');
  const [branchId, setBranchId] = useState(order?.branch_id ? String(order.branch_id) : '');
  const [terms, setTerms] = useState(order?.payment_terms_days != null ? String(order.payment_terms_days) : '');
  const [address, setAddress] = useState(order?.delivery_address ?? '');
  const [notes, setNotes] = useState(order?.notes ?? '');
  const [termsText, setTermsText] = useState(order?.terms ?? '');
  const [shipping, setShipping] = useState(order && toUnits(order.shipping_cost) > 0n ? editable(order.shipping_cost) : '');
  const [lines, setLines] = useState<DraftLine[]>(
    order?.lines?.length
      ? order.lines.map((l) =>
          newLine({
            product_id: l.product_id,
            description: l.description,
            quantity: editable(l.quantity),
            uom: l.uom ?? '',
            unit_price: editable(l.unit_price),
            discount_percent: toUnits(l.discount_percent) ? editable(l.discount_percent) : '',
            tax_rate: toUnits(l.tax_rate) ? editable(l.tax_rate) : '',
          }),
        )
      : [newLine()],
  );
  const [errors, setErrors] = useState<SplitErrors>(NO_ERRORS);

  const supplier = supplierList.data?.find((s) => String(s.id) === supplierId);
  const currency = supplier?.currency ?? order?.currency ?? companyCurrency;

  const save = async () => {
    setErrors(NO_ERRORS);
    if (!supplierId) {
      setErrors({ ...NO_ERRORS, header: { supplier_id: 'Choose a supplier' }, message: 'Choose a supplier.' });
      return;
    }
    const { rows, keys } = collectLines(lines);
    if (rows.length === 0) {
      setErrors({ ...NO_ERRORS, message: 'Add at least one line.' });
      return;
    }
    const body: PurchaseOrderInput = {
      supplier_id: Number(supplierId),
      branch_id: branchId ? Number(branchId) : null,
      order_date: orderDate,
      expected_date: expected || null,
      currency,
      shipping_cost: shipping || '0',
      warehouse_id: warehouseId ? Number(warehouseId) : null,
      delivery_address: address.trim() || null,
      payment_terms_days: terms === '' ? null : Number(terms),
      notes: notes.trim() || null,
      terms: termsText.trim() || null,
      lines: rows.map((l) => ({
        product_id: l.product_id,
        description: l.description.trim(),
        quantity: l.quantity,
        uom: l.uom.trim() || null,
        unit_price: l.unit_price || '0',
        discount_percent: l.discount_percent || '0',
        tax_rate: l.tax_rate || '0',
      })),
    };
    try {
      if (order) await update.mutateAsync({ id: order.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      setErrors(splitServerErrors(e as ApiError, keys));
    }
  };

  const h = errors.header;
  return (
    <Card>
      <CardHeader title={order ? `Edit ${order.number}` : 'New purchase order'} description="Totals are previewed here and recalculated by the server on save." />
      <CardBody>
        <div className={styles.stack}>
          {errors.message && <Alert variant="danger">{errors.message}</Alert>}
          {supplier && !supplier.is_approved && <Alert variant="warning">{supplier.name} has not been approved for purchasing yet.</Alert>}
          <FormGrid columns={3}>
            <FormField label="Supplier" required error={h.supplier_id}>
              <Select
                value={supplierId}
                placeholder={supplierList.isLoading ? 'Loading…' : 'Select supplier'}
                onChange={(e) => {
                  setSupplierId(e.target.value);
                  const s = supplierList.data?.find((x) => String(x.id) === e.target.value);
                  if (s) setTerms(String(s.payment_terms_days));
                }}
                options={(supplierList.data ?? []).map((s) => ({ value: String(s.id), label: `${s.name} (${s.code})` }))}
              />
            </FormField>
            <FormField label="Order date" required error={h.order_date}>
              <DateInput value={orderDate} onValueChange={(v) => setOrderDate(v ?? '')} />
            </FormField>
            <FormField label="Expected delivery" error={h.expected_date}>
              <DateInput value={expected} min={orderDate || undefined} onValueChange={(v) => setExpected(v ?? '')} />
            </FormField>
            <FormField label="Receiving warehouse" error={h.warehouse_id} hint={warehouses.isError ? 'Warehouse list unavailable' : undefined}>
              <Select value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} options={[{ value: '', label: 'Not set' }, ...(warehouses.data ?? []).map((w) => ({ value: String(w.id), label: `${w.name} (${w.code})` }))]} />
            </FormField>
            <FormField label="Branch" error={h.branch_id}>
              <Select value={branchId} onChange={(e) => setBranchId(e.target.value)} options={[{ value: '', label: 'None' }, ...branches.map((b) => ({ value: String(b.id), label: b.name }))]} />
            </FormField>
            <FormField label="Payment terms (days)" error={h.payment_terms_days} hint={`Currency: ${currency}`}>
              <Input inputMode="numeric" value={terms} onChange={(e) => setTerms(e.target.value.replace(/\D/g, '').slice(0, 3))} />
            </FormField>
            <FormField label="Delivery address" span="full" error={h.delivery_address}>
              <Textarea rows={2} maxLength={500} value={address} onChange={(e) => setAddress(e.target.value)} />
            </FormField>
          </FormGrid>

          <LinesEditor mode="order" lines={lines} onChange={setLines} currency={currency} rowErrors={errors.rows} linesError={h.lines} shipping={shipping} onShippingChange={setShipping} />
          {h.shipping_cost && <Alert variant="danger">{h.shipping_cost}</Alert>}

          <FormGrid columns={2}>
            <FormField label="Notes to supplier" error={h.notes}>
              <Textarea rows={3} maxLength={2000} value={notes} onChange={(e) => setNotes(e.target.value)} />
            </FormField>
            <FormField label="Terms & conditions" error={h.terms}>
              <Textarea rows={3} maxLength={5000} value={termsText} onChange={(e) => setTermsText(e.target.value)} />
            </FormField>
          </FormGrid>
          <div className={styles.row} style={{ justifyContent: 'flex-end' }}>
            <Button variant="secondary" onClick={() => (onDone ? onDone() : router.back())}>Cancel</Button>
            <Button onClick={save} loading={create.isPending || update.isPending}>Save draft</Button>
          </div>
        </div>
      </CardBody>
    </Card>
  );
}

function ReceiptsTab({ orderId }: { orderId: number }) {
  const q = goodsReceipts.useList({ 'filter[purchase_order_id]': orderId, per_page: 50, sort: '-received_date' });
  if (q.error) return <ErrorState size="sm" error={q.error} onRetry={() => void q.refetch()} />;
  if (q.isLoading) return <SkeletonTable rows={2} cols={5} />;
  const rows = q.data?.data ?? [];
  if (!rows.length) return <EmptyState size="sm" title="No goods received yet" />;
  return (
    <div className={crud.tableScroll}>
      <table className={crud.lines} style={{ minWidth: 520 }}>
        <thead><tr><th>Number</th><th>Received</th><th>Received by</th><th>Delivery note</th><th>Status</th></tr></thead>
        <tbody>
          {rows.map((g) => (
            <tr key={g.id}>
              <td><Link href={`/procurement/receipts/${g.id}`} className={crud.mono}>{g.number}</Link></td>
              <td>{formatDate(g.received_date)}</td>
              <td>{g.receiver?.name ?? '—'}</td>
              <td>{g.supplier_delivery_note ?? '—'}</td>
              <td><DocStatus status={g.status} /></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

type Verb = 'submit' | 'approve' | 'send' | 'close' | 'delete';

export default function PurchaseOrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const can = useCan();
  const isNew = id === 'new';
  const q = purchaseOrders.useOne(isNew ? undefined : id);
  const receipts = goodsReceipts.useList({ 'filter[purchase_order_id]': id, 'filter[status]': 'confirmed', per_page: 1, sort: '-received_date' }, { enabled: !isNew });
  const warehouses = useWarehouses();
  const submit = purchaseOrders.useAction('submit', { success: 'Submitted for approval' });
  const approve = purchaseOrders.useAction('approve', { success: 'Purchase order approved' });
  const send = purchaseOrders.useAction('send', { success: 'Marked as sent to supplier' });
  const close = purchaseOrders.useAction('close', { success: 'Purchase order closed' });
  const cancel = purchaseOrders.useAction<{ reason: string }>('cancel', { success: 'Purchase order cancelled' });
  const del = purchaseOrders.useDelete({ onSuccess: () => router.replace('/procurement/orders') });
  const [editing, setEditing] = useState(false);
  const [confirm, setConfirm] = useState<Verb | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [returning, setReturning] = useState(false);
  const [tab, setTab] = useState('lines');

  if (isNew) {
    if (!can('purchase_orders.manage')) return <Alert variant="warning">You don’t have permission to create purchase orders.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New purchase order" backHref="/procurement/orders" backLabel="Purchase orders" />
        <Editor order={null} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard />;
  const o = q.data;
  const canManage = can('purchase_orders.manage');

  if (editing && o.status === 'draft' && canManage) {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${o.number}`} backHref="/procurement/orders" backLabel="Purchase orders" />
        <Editor order={o} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const receivable = o.status === 'approved' || o.status === 'partially_received';
  const showReceived = !['draft', 'submitted'].includes(o.status);
  const hasReturnable = (o.lines ?? []).some((l) => toUnits(l.quantity_received) - toUnits(l.quantity_returned) > 0n);
  const lastReceipt = receipts.data?.data[0];
  const warehouse = warehouses.data?.find((w) => w.id === o.warehouse_id);

  const steps: TimelineStep[] = [
    { label: 'Created', at: o.created_at, state: 'done' },
    { label: 'Submitted', at: o.submitted_at, state: o.submitted_at ? 'done' : 'pending' },
    { label: 'Approved', at: o.approved_at, detail: o.approver?.name, state: o.approved_at ? 'done' : 'pending' },
    { label: 'Sent to supplier', at: o.sent_at, state: o.sent_at ? 'done' : 'pending' },
    {
      label: o.status === 'partially_received' ? 'Partially received' : 'Received',
      at: null,
      detail: lastReceipt ? `last receipt ${formatDate(lastReceipt.received_date)}` : undefined,
      state: o.status === 'received' || (o.status === 'closed' && lastReceipt) ? 'done' : 'pending',
    },
  ];
  if (o.status === 'closed') steps.push({ label: 'Closed', at: o.closed_at, state: 'done' });
  if (o.status === 'cancelled') steps.push({ label: 'Cancelled', at: o.cancelled_at, detail: o.cancelled_reason, state: 'bad' });

  const run = async (fn: () => Promise<unknown>) => {
    try {
      await fn();
    } finally {
      setConfirm(null);
    }
  };

  const confirmCopy: Record<Verb, { title: string; description: string; label: string; destructive?: boolean; fn: () => Promise<unknown> }> = {
    submit: { title: `Submit ${o.number}?`, description: 'The order is locked for editing and sent to an approver.', label: 'Submit for approval', fn: () => submit.mutateAsync({ id: o.id }) },
    approve: { title: `Approve ${o.number}?`, description: `This commits ${formatMoney(o.total, o.currency)} of spend with ${o.supplier?.name ?? 'the supplier'}.`, label: 'Approve order', fn: () => approve.mutateAsync({ id: o.id }) },
    send: { title: `Mark ${o.number} as sent?`, description: 'Records that the order has been sent to the supplier.', label: 'Mark as sent', fn: () => send.mutateAsync({ id: o.id }) },
    close: { title: `Close ${o.number}?`, description: 'No further goods will be received against a closed order.', label: 'Close order', fn: () => close.mutateAsync({ id: o.id }) },
    delete: { title: `Delete draft ${o.number}?`, description: 'The draft will be removed.', label: 'Delete draft', destructive: true, fn: () => del.mutateAsync(o.id) },
  };
  const cc = confirm ? confirmCopy[confirm] : null;

  return (
    <div className={styles.stack}>
      <PageHeader
        title={o.number}
        description={o.supplier ? <Link href={`/procurement/suppliers/${o.supplier.id}`}>{o.supplier.name}</Link> : undefined}
        backHref="/procurement/orders"
        backLabel="Purchase orders"
        meta={<DocStatus status={o.status} />}
        actions={
          <>
            {o.status === 'draft' && canManage && <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>}
            {['draft', 'submitted', 'approved'].includes(o.status) && canManage && (
              <Button variant="ghost" leftIcon={<Ban size={16} />} onClick={() => setCancelling(true)}>Cancel</Button>
            )}
            {o.status === 'draft' && canManage && <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {o.status === 'draft' && canManage && <Button leftIcon={<Send size={16} />} onClick={() => setConfirm('submit')}>Submit</Button>}
            {o.status === 'submitted' && can('purchase_orders.approve') && <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            {receivable && canManage && (
              <Button variant="secondary" leftIcon={<Mail size={16} />} onClick={() => setConfirm('send')}>{o.sent_at ? 'Mark re-sent' : 'Mark as sent'}</Button>
            )}
            {['approved', 'partially_received', 'received'].includes(o.status) && canManage && (
              <Button variant="secondary" leftIcon={<Lock size={16} />} onClick={() => setConfirm('close')}>Close</Button>
            )}
            {hasReturnable && can('goods_receipts.manage') && o.status !== 'cancelled' && (
              <Button variant="secondary" leftIcon={<Undo2 size={16} />} onClick={() => setReturning(true)}>Return goods</Button>
            )}
            {receivable && can('goods_receipts.manage') && (
              <Button leftIcon={<PackagePlus size={16} />} href={`/procurement/receipts/new?po=${o.id}`}>Receive goods</Button>
            )}
          </>
        }
      />

      {o.status === 'cancelled' && <Alert variant="danger" title="Cancelled">{o.cancelled_reason ?? 'This order was cancelled.'}</Alert>}
      {o.requisition && <Alert variant="info">Created from requisition <Link href={`/procurement/requisitions/${o.requisition.id}`}>{o.requisition.number}</Link>.</Alert>}

      <div className={crud.detailGrid}>
        <Card>
          <Tabs value={tab} onValueChange={setTab}>
            <div style={{ padding: '0 16px' }}>
              <TabList>
                <Tab value="lines" count={o.lines?.length}>Lines</Tab>
                <Tab value="receipts" count={o.goods_receipts_count}>Receipts</Tab>
                <Tab value="returns">Returns</Tab>
              </TabList>
            </div>
            <TabPanel value="lines">
              <div className={crud.tableScroll}>
                <table className={crud.lines} style={{ minWidth: showReceived ? 880 : 680 }}>
                  <thead>
                    <tr>
                      <th>#</th><th>Description</th><th className={crud.r}>Qty</th><th className={crud.r}>Unit price</th><th className={crud.r}>Disc.</th><th className={crud.r}>Tax</th>
                      {showReceived && <><th className={crud.r}>Received</th><th className={crud.r}>Returned</th><th className={crud.r}>Remaining</th></>}
                      <th className={crud.r}>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {o.lines?.map((l) => (
                      <tr key={l.id}>
                        <td className={crud.muted}>{l.line_no}</td>
                        <td>{l.description}{l.product_id && <span className={styles.linked}>Catalogue item #{l.product_id}</span>}</td>
                        <td className={crud.r}>{formatQty(l.quantity)} {l.uom ?? ''}</td>
                        <td className={crud.r}>{formatMoney(l.unit_price, o.currency)}</td>
                        <td className={crud.r}>{toUnits(l.discount_percent) ? `${formatQty(l.discount_percent)}%` : '—'}</td>
                        <td className={crud.r}>{toUnits(l.tax_rate) ? `${formatQty(l.tax_rate)}%` : '—'}</td>
                        {showReceived && (
                          <>
                            <td className={crud.r}>{formatQty(l.quantity_received)}</td>
                            <td className={crud.r}>{formatQty(l.quantity_returned)}</td>
                            <td className={crud.r}>{formatQty(l.quantity_remaining)}</td>
                          </>
                        )}
                        <td className={crud.r}>{formatMoney(l.line_total, o.currency)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div style={{ padding: 16 }}>
                <div className={crud.totals}>
                  <span>Subtotal</span><span>{formatMoney(o.subtotal, o.currency)}</span>
                  <span>Discount</span><span>{formatMoney(o.discount_total, o.currency)}</span>
                  <span>Tax</span><span>{formatMoney(o.tax_total, o.currency)}</span>
                  <span>Shipping</span><span>{formatMoney(o.shipping_cost, o.currency)}</span>
                  <span className={crud.grand}>Total</span><span className={crud.grand}>{formatMoney(o.total, o.currency)}</span>
                </div>
              </div>
            </TabPanel>
            <TabPanel value="receipts"><div style={{ padding: 16 }}><ReceiptsTab orderId={o.id} /></div></TabPanel>
            <TabPanel value="returns"><div style={{ padding: 16 }}><ReturnsPanel orderId={o.id} currency={o.currency} /></div></TabPanel>
          </Tabs>
        </Card>

        <div className={styles.stack}>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>Supplier</dt><dd>{o.supplier ? `${o.supplier.name} (${o.supplier.code})` : '—'}</dd>
                <dt>Order date</dt><dd>{formatDate(o.order_date)}</dd>
                <dt>Expected</dt><dd>{formatDate(o.expected_date)}</dd>
                <dt>Currency</dt><dd>{o.currency}</dd>
                <dt>Payment terms</dt><dd>{o.payment_terms_days != null ? `${o.payment_terms_days} days` : '—'}</dd>
                <dt>Warehouse</dt><dd>{warehouse ? `${warehouse.name} (${warehouse.code})` : o.warehouse_id ? `#${o.warehouse_id}` : '—'}</dd>
                <dt>Delivery address</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{o.delivery_address ?? '—'}</dd>
                <dt>Created</dt><dd>{formatDateTime(o.created_at)}</dd>
                {o.notes && (<><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{o.notes}</dd></>)}
                {o.terms && (<><dt>Terms</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{o.terms}</dd></>)}
              </dl>
            </CardBody>
          </Card>
          <Card>
            <CardHeader title="Status timeline" />
            <CardBody><Timeline steps={steps} /></CardBody>
          </Card>
        </div>
      </div>

      <ConfirmDialog
        open={cc !== null}
        onClose={() => setConfirm(null)}
        title={cc?.title ?? ''}
        description={cc?.description}
        destructive={cc?.destructive}
        confirmLabel={cc?.label}
        onConfirm={() => (cc ? run(cc.fn) : undefined)}
      />
      {cancelling && (
        <ReasonDialog
          open
          onClose={() => setCancelling(false)}
          title={`Cancel ${o.number}?`}
          description="Cancelled orders can’t be reopened. Orders with received goods must be closed instead."
          confirmLabel="Cancel order"
          onSubmit={(reason) => cancel.mutateAsync({ id: o.id, body: { reason } })}
        />
      )}
      {returning && <ReturnModal order={o} open onClose={() => { setReturning(false); setTab('returns'); }} />}
    </div>
  );
}
