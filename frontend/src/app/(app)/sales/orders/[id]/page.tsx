'use client';

import { Suspense, use, useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { useMutation, useQueries, useQueryClient } from '@tanstack/react-query';
import { Ban, CheckCircle2, ClipboardCheck, Copy, PackageCheck, Pencil, Trash2, Truck } from 'lucide-react';
import {
  Alert, Button, Card, CardBody, CardHeader, ConfirmDialog, DateInput, EmptyState, ErrorState, FormField, FormGrid, FormSection, Input, Modal, PageHeader, Select, SkeletonCard, Textarea, useToast,
} from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatDate, formatDateTime } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { cn } from '@/lib/cn';
import { useAppSelector } from '@/store';
import { customers, deliveries, salesApi, salesOrders, type Delivery, type SalesOrder } from '@/features/sales/api';
import { compareDecimals, formatQty, fromScaled, isPositive, sanitizeDecimal, toScaled } from '@/features/sales/decimal';
import { blankLine, DocumentLinesTable, linesFromDocument, linesToPayload, remapLineErrors, SalesLinesEditor, TotalsBlock, type DraftLine } from '@/features/sales/SalesLinesEditor';
import { flattenErrors, nullable, SalesStatus, useCustomerOptions, useUserOptions, useWarehouseOptions } from '@/features/sales/shared';
import crud from '@/components/crud/crud.module.scss';
import styles from '@/features/sales/sales.module.scss';

const todayIso = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const SHIP_KEYS = ['name', 'line1', 'line2', 'city', 'state', 'postal_code', 'country'] as const;
type ShipKey = (typeof SHIP_KEYS)[number];
type Ship = Record<ShipKey, string>;
const SHIP_FIELD: Record<ShipKey, string> = {
  name: 'shipping_name',
  line1: 'shipping_address_line1',
  line2: 'shipping_address_line2',
  city: 'shipping_city',
  state: 'shipping_state',
  postal_code: 'shipping_postal_code',
  country: 'shipping_country',
};

// ---------------------------------------------------------------------------
// Editor
// ---------------------------------------------------------------------------

function OrderEditor({ order, initialCustomerId, onDone }: { order: SalesOrder | null; initialCustomerId?: string; onDone: () => void }) {
  const router = useRouter();
  const companyCurrency = useAppSelector((s) => s.session.current_company?.currency ?? 'USD');
  const [customerId, setCustomerId] = useState(order ? String(order.customer_id) : (initialCustomerId ?? ''));
  const [orderDate, setOrderDate] = useState(order?.order_date ?? todayIso());
  const [expected, setExpected] = useState<string | null>(order?.expected_delivery_date ?? null);
  const [warehouseId, setWarehouseId] = useState(order?.warehouse_id ? String(order.warehouse_id) : '');
  const [salesRepId, setSalesRepId] = useState(order?.sales_rep_id ? String(order.sales_rep_id) : '');
  const [ship, setShip] = useState<Ship>(() => {
    const s = order?.shipping_address;
    return { name: s?.name ?? '', line1: s?.line1 ?? '', line2: s?.line2 ?? '', city: s?.city ?? '', state: s?.state ?? '', postal_code: s?.postal_code ?? '', country: s?.country ?? '' };
  });
  const [notes, setNotes] = useState(order?.notes ?? '');
  const [terms, setTerms] = useState(order?.terms ?? '');
  const [lines, setLines] = useState<DraftLine[]>(order ? linesFromDocument(order.lines) : [blankLine()]);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const customerOpts = useCustomerOptions(order?.customer ?? null);
  const reps = useUserOptions([order?.sales_rep]);
  const warehouses = useWarehouseOptions();
  const customer = customers.useOne(customerId || undefined);
  const currency = order?.currency ?? customer.data?.currency ?? companyCurrency;
  const create = salesOrders.useCreate({ onSuccess: (o) => router.replace(`/sales/orders/${o.id}`) });
  const update = salesOrders.useUpdate({ onSuccess: onDone });

  const copyFromCustomer = () => {
    const c = customer.data;
    if (!c) return;
    const a = c.shipping_address;
    setShip({ name: c.name, line1: a.line1 ?? '', line2: a.line2 ?? '', city: a.city ?? '', state: a.state ?? '', postal_code: a.postal_code ?? '', country: a.country ?? '' });
  };

  const save = async () => {
    setError(null);
    setFieldErrors({});
    const { payload, indexMap } = linesToPayload(lines);
    if (!customerId) return setFieldErrors({ customer_id: 'Choose a customer' });
    if (payload.length === 0) return setFieldErrors({ lines: 'Add at least one line' });
    const body: Record<string, unknown> & { customer_id: number; lines: typeof payload } = {
      customer_id: Number(customerId),
      order_date: orderDate,
      expected_delivery_date: expected || null,
      warehouse_id: warehouseId ? Number(warehouseId) : null,
      notes: nullable(notes),
      terms: nullable(terms),
      lines: payload,
    };
    if (salesRepId || order) body.sales_rep_id = salesRepId ? Number(salesRepId) : null;
    for (const k of SHIP_KEYS) {
      const v = nullable(ship[k]);
      body[SHIP_FIELD[k]] = k === 'country' ? (v?.toUpperCase() ?? null) : v;
    }
    try {
      if (order) await update.mutateAsync({ id: order.id, body });
      else await create.mutateAsync(body);
    } catch (e) {
      const { fields, message } = flattenErrors(e as ApiError);
      setFieldErrors(remapLineErrors(fields, indexMap));
      setError(message);
    }
  };

  const shipInput = (k: ShipKey, label: string, span?: 'full') => (
    <FormField label={label} span={span} error={fieldErrors[SHIP_FIELD[k]]}>
      <Input value={ship[k]} maxLength={k === 'country' ? 2 : 200} onChange={(e) => setShip((s) => ({ ...s, [k]: e.target.value }))} />
    </FormField>
  );

  return (
    <Card>
      <CardHeader title={order ? `Edit ${order.number}` : 'New sales order'} description="Totals are recalculated by the server on save." />
      <CardBody>
        {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
        <FormGrid columns={4}>
          <FormField label="Customer" required span={2} error={fieldErrors.customer_id}>
            <Select value={customerId} onChange={(e) => setCustomerId(e.target.value)} placeholder={customerOpts.isLoading ? 'Loading…' : 'Choose a customer'} options={customerOpts.options} disabled={!!order} />
          </FormField>
          <FormField label="Order date" required error={fieldErrors.order_date}><DateInput value={orderDate} onValueChange={(v) => setOrderDate(v ?? todayIso())} /></FormField>
          <FormField label="Expected delivery" error={fieldErrors.expected_delivery_date}><DateInput value={expected} min={orderDate} onValueChange={setExpected} /></FormField>
          <FormField label="Warehouse" span={2} error={fieldErrors.warehouse_id} hint={!warehouses.allowed ? 'You can’t view warehouses; enter the warehouse ID.' : undefined}>
            {warehouses.allowed ? (
              <Select value={warehouseId} onChange={(e) => setWarehouseId(e.target.value)} placeholder={warehouses.isLoading ? 'Loading…' : 'Not set'} options={warehouses.options} />
            ) : (
              <Input inputMode="numeric" value={warehouseId} onChange={(e) => setWarehouseId(e.target.value.replace(/\D/g, ''))} />
            )}
          </FormField>
          <FormField label="Sales rep" span={2} error={fieldErrors.sales_rep_id} hint={!order && !salesRepId ? 'Left blank, the customer’s rep applies.' : undefined}>
            <Select value={salesRepId} onChange={(e) => setSalesRepId(e.target.value)} placeholder="Unassigned" options={reps.options} />
          </FormField>
        </FormGrid>

        <div style={{ marginTop: 20 }}>
          <FormSection
            title="Ship to"
            description={!order ? 'Leave blank to use the customer’s shipping address.' : undefined}
          >
            <div>
              <Button size="sm" variant="secondary" leftIcon={<Copy size={14} />} onClick={copyFromCustomer} disabled={!customer.data}>Copy from customer</Button>
            </div>
            <FormGrid columns={3}>
              {shipInput('name', 'Recipient')}
              {shipInput('line1', 'Address line 1')}
              {shipInput('line2', 'Address line 2')}
              {shipInput('city', 'City')}
              {shipInput('state', 'State / region')}
              <FormGrid columns={2}>
                {shipInput('postal_code', 'Postal code')}
                {shipInput('country', 'Country')}
              </FormGrid>
            </FormGrid>
          </FormSection>
        </div>

        <div style={{ marginTop: 8 }}>
          <SalesLinesEditor lines={lines} onChange={setLines} currency={currency} errors={fieldErrors} />
        </div>

        <FormGrid columns={2} style={{ marginTop: 20 }}>
          <FormField label="Notes" error={fieldErrors.notes}><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={5000} /></FormField>
          <FormField label="Terms" error={fieldErrors.terms}><Textarea rows={3} value={terms} onChange={(e) => setTerms(e.target.value)} maxLength={5000} /></FormField>
        </FormGrid>

        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 16 }}>
          <Button variant="secondary" onClick={() => (order ? onDone() : router.back())}>Cancel</Button>
          <Button onClick={save} loading={create.isPending || update.isPending}>{order ? 'Save changes' : 'Save draft'}</Button>
        </div>
      </CardBody>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Deliveries
// ---------------------------------------------------------------------------

const isOpen = (d: Delivery) => d.status === 'pending' || d.status === 'in_transit';

/** Open deliveries' lines (the order payload doesn't include delivery lines). */
function useDeliveryDetails(order: SalesOrder) {
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const list = order.deliveries ?? [];
  const results = useQueries({
    queries: list.map((d) => ({
      queryKey: [companyId, 'deliveries', 'one', d.id, d.updated_at],
      queryFn: () => salesApi.delivery(d.id),
      staleTime: 30_000,
    })),
  });
  const byId = new Map<number, Delivery>();
  results.forEach((r) => r.data && byId.set(r.data.id, r.data));
  const reserved = new Map<number, bigint>();
  for (const d of list.filter(isOpen)) {
    for (const l of byId.get(d.id)?.lines ?? []) reserved.set(l.sales_order_line_id, (reserved.get(l.sales_order_line_id) ?? 0n) + toScaled(l.quantity));
  }
  const loading = results.some((r) => r.isLoading);
  return { byId, reserved, loading };
}

function CreateDeliveryModal({ order, reserved, onClose }: { order: SalesOrder; reserved: Map<number, bigint>; onClose: () => void }) {
  const toast = useToast();
  const qc = useQueryClient();
  const remaining = (order.lines ?? []).map((l) => ({
    line: l,
    remaining: fromScaled(toScaled(l.quantity) - toScaled(l.quantity_delivered ?? '0') - (reserved.get(l.id) ?? 0n)),
  }));
  const [qty, setQty] = useState<Record<number, string>>(() =>
    Object.fromEntries(remaining.map((r) => [r.line.id, isPositive(r.remaining) ? formatQty(r.remaining).replace(/,/g, '') : ''])),
  );
  const [date, setDate] = useState<string | null>(todayIso());
  const [carrier, setCarrier] = useState('');
  const [tracking, setTracking] = useState('');
  const [notes, setNotes] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const rows = remaining.filter((r) => isPositive(qty[r.line.id]));
  const over = remaining.filter((r) => compareDecimals(qty[r.line.id] || '0', r.remaining) > 0);

  const m = useMutation({
    mutationFn: () =>
      salesApi.createDelivery(order.id, {
        delivery_date: date ?? undefined,
        carrier: nullable(carrier),
        tracking_number: nullable(tracking),
        notes: nullable(notes),
        lines: rows.map((r) => ({ sales_order_line_id: r.line.id, quantity: qty[r.line.id] })),
      }),
    onSuccess: (d) => {
      void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('sales-orders') || q.queryKey.includes('deliveries') });
      toast.success(`Delivery ${d.number} created`);
      onClose();
    },
    onError: (e: ApiError) => {
      const { fields, message } = flattenErrors(e);
      const mapped: Record<string, string> = {};
      for (const [k, v] of Object.entries(fields)) {
        const mm = /^lines\.(\d+)\.(.+)$/.exec(k);
        const row = mm ? rows[Number(mm[1])] : undefined;
        mapped[row ? `line-${row.line.id}` : k] = v;
      }
      setFieldErrors(mapped);
      setError(message);
    },
  });

  return (
    <Modal
      open
      onClose={onClose}
      size="lg"
      title={`Create delivery for ${order.number}`}
      description="Quantities default to what is still undelivered (excluding pending and in-transit deliveries)."
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button leftIcon={<Truck size={16} />} onClick={() => m.mutate()} loading={m.isPending} disabled={rows.length === 0 || over.length > 0}>Create delivery</Button>
        </>
      }
    >
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
      <div className={crud.tableScroll}>
        <table className={crud.lines} style={{ minWidth: 560 }}>
          <thead>
            <tr><th>Item</th><th className={crud.r}>Ordered</th><th className={crud.r}>Remaining</th><th className={crud.r} style={{ width: 140 }}>Deliver now</th></tr>
          </thead>
          <tbody>
            {remaining.map(({ line, remaining: rem }) => {
              const err = fieldErrors[`line-${line.id}`] ?? (compareDecimals(qty[line.id] || '0', rem) > 0 ? `Max ${formatQty(rem)}` : undefined);
              return (
                <tr key={line.id}>
                  <td>{line.description}</td>
                  <td className={crud.r}>{formatQty(line.quantity)} {line.uom ?? ''}</td>
                  <td className={crud.r}>{formatQty(rem)}</td>
                  <td>
                    <Input
                      className={styles.qtyInput}
                      aria-label={`Quantity to deliver for ${line.description}`}
                      inputMode="decimal"
                      value={qty[line.id] ?? ''}
                      disabled={!isPositive(rem)}
                      invalid={!!err}
                      onChange={(e) => setQty((q) => ({ ...q, [line.id]: sanitizeDecimal(e.target.value) }))}
                    />
                    {err && <span className={styles.lineError}>{err}</span>}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <FormGrid columns={3} style={{ marginTop: 16 }}>
        <FormField label="Delivery date" error={fieldErrors.delivery_date}><DateInput value={date} onValueChange={setDate} /></FormField>
        <FormField label="Carrier" error={fieldErrors.carrier}><Input value={carrier} maxLength={100} onChange={(e) => setCarrier(e.target.value)} placeholder="e.g. TCS, DHL, own fleet" /></FormField>
        <FormField label="Tracking no." error={fieldErrors.tracking_number}><Input value={tracking} maxLength={100} onChange={(e) => setTracking(e.target.value)} /></FormField>
        <FormField label="Notes" span="full" error={fieldErrors.notes}><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} /></FormField>
      </FormGrid>
      <p className={styles.previewNote}>Manual tracking — carrier and tracking number are recorded for reference; there is no live GPS feed.</p>
    </Modal>
  );
}

function DeliveriesCard({ order, details, canManage, onCreate, canCreate }: { order: SalesOrder; details: ReturnType<typeof useDeliveryDetails>; canManage: boolean; onCreate: () => void; canCreate: boolean }) {
  const qc = useQueryClient();
  const refreshOrder = () => void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes('sales-orders') });
  const ship = deliveries.useAction<{ carrier: string | null; tracking_number: string | null }>('ship', { success: 'Marked as shipped', onSuccess: refreshOrder });
  const deliver = deliveries.useAction('deliver', { success: 'Marked as delivered', onSuccess: refreshOrder });
  const cancel = deliveries.useAction<{ reason: string | null }>('cancel', { success: 'Delivery cancelled', onSuccess: refreshOrder });
  const [shipping, setShipping] = useState<Delivery | null>(null);
  const [carrier, setCarrier] = useState('');
  const [tracking, setTracking] = useState('');
  const [delivering, setDelivering] = useState<Delivery | null>(null);
  const [cancelling, setCancelling] = useState<Delivery | null>(null);
  const [reason, setReason] = useState('');
  const list = order.deliveries ?? [];

  return (
    <Card>
      <CardHeader
        title="Deliveries"
        description="Manual tracking — no live GPS. Carrier and tracking numbers are recorded for reference."
        actions={canCreate ? <Button size="sm" leftIcon={<Truck size={14} />} onClick={onCreate}>Create delivery</Button> : undefined}
      />
      <CardBody padding="none">
        {list.length === 0 ? (
          <EmptyState size="sm" icon={<Truck size={20} />} title="No deliveries yet" description={canCreate ? 'Create a delivery to ship some or all of the ordered quantities.' : 'Deliveries can be created once the order is approved.'} />
        ) : (
          <div className={crud.tableScroll}>
            <table className={crud.lines} style={{ minWidth: 720 }}>
              <thead>
                <tr><th>Delivery</th><th>Date</th><th>Status</th><th>Carrier / tracking</th><th>Items</th><th aria-label="Actions" /></tr>
              </thead>
              <tbody>
                {list.map((d) => {
                  const detail = details.byId.get(d.id);
                  return (
                    <tr key={d.id}>
                      <td><span className={crud.mono}>{d.number}</span></td>
                      <td>
                        {formatDate(d.delivery_date)}
                        {d.shipped_at && <div className={crud.muted} style={{ fontSize: 12 }}>Shipped {formatDateTime(d.shipped_at)}</div>}
                        {d.delivered_at && <div className={crud.muted} style={{ fontSize: 12 }}>Delivered {formatDateTime(d.delivered_at)}</div>}
                      </td>
                      <td><SalesStatus status={d.status} /></td>
                      <td>
                        {d.carrier ?? <span className={crud.muted}>—</span>}
                        {d.tracking_number && <div className={crud.mono} style={{ fontSize: 12 }}>{d.tracking_number}</div>}
                        <div className={crud.muted} style={{ fontSize: 12 }}>Manual tracking</div>
                      </td>
                      <td>
                        {detail?.lines ? (
                          detail.lines.map((l) => <div key={l.id} style={{ fontSize: 12 }}>{formatQty(l.quantity)} × {l.description ?? `Line ${l.sales_order_line_id}`}</div>)
                        ) : details.loading ? <span className={crud.muted}>Loading…</span> : <span className={crud.muted}>—</span>}
                      </td>
                      <td>
                        {canManage && isOpen(d) && (
                          <div className={styles.actionsRow}>
                            {d.status === 'pending' && <Button size="sm" variant="secondary" onClick={() => { setCarrier(d.carrier ?? ''); setTracking(d.tracking_number ?? ''); setShipping(d); }}>Ship</Button>}
                            <Button size="sm" variant="secondary" leftIcon={<PackageCheck size={14} />} onClick={() => setDelivering(d)}>Deliver</Button>
                            <Button size="sm" variant="ghost" onClick={() => { setReason(''); setCancelling(d); }}>Cancel</Button>
                          </div>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </CardBody>

      <Modal
        open={shipping !== null}
        onClose={() => setShipping(null)}
        size="sm"
        title={`Ship ${shipping?.number}`}
        description="Marks the delivery as in transit. Tracking is manual — no live GPS."
        footer={
          <>
            <Button variant="secondary" onClick={() => setShipping(null)}>Cancel</Button>
            <Button
              loading={ship.isPending}
              onClick={async () => {
                if (!shipping) return;
                try {
                  await ship.mutateAsync({ id: shipping.id, body: { carrier: nullable(carrier), tracking_number: nullable(tracking) } });
                  setShipping(null);
                } catch {
                  // toast shown
                }
              }}
            >
              Mark shipped
            </Button>
          </>
        }
      >
        <FormGrid columns={1}>
          <FormField label="Carrier"><Input value={carrier} maxLength={100} onChange={(e) => setCarrier(e.target.value)} autoFocus /></FormField>
          <FormField label="Tracking no."><Input value={tracking} maxLength={100} onChange={(e) => setTracking(e.target.value)} /></FormField>
        </FormGrid>
      </Modal>
      <ConfirmDialog
        open={delivering !== null}
        onClose={() => setDelivering(null)}
        title={`Mark ${delivering?.number} as delivered?`}
        description="Delivered quantities are added to the order lines and the order status rolls forward."
        confirmLabel="Mark delivered"
        onConfirm={async () => {
          if (delivering) await deliver.mutateAsync({ id: delivering.id }).catch(() => undefined);
          setDelivering(null);
        }}
      />
      <Modal
        open={cancelling !== null}
        onClose={() => setCancelling(null)}
        size="sm"
        title={`Cancel ${cancelling?.number}?`}
        description="The reserved quantities become available for a new delivery."
        footer={
          <>
            <Button variant="secondary" onClick={() => setCancelling(null)}>Keep delivery</Button>
            <Button
              variant="danger"
              loading={cancel.isPending}
              onClick={async () => {
                if (!cancelling) return;
                try {
                  await cancel.mutateAsync({ id: cancelling.id, body: { reason: nullable(reason) } });
                  setCancelling(null);
                } catch {
                  // toast shown
                }
              }}
            >
              Cancel delivery
            </Button>
          </>
        }
      >
        <FormField label="Reason" hint="Optional"><Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} /></FormField>
      </Modal>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Detail
// ---------------------------------------------------------------------------

function OrderDetail({ o }: { o: SalesOrder }) {
  const router = useRouter();
  const can = useCan();
  const warehouses = useWarehouseOptions();
  const canManage = can('sales_orders.manage');
  const [editing, setEditing] = useState(false);
  const [confirm, setConfirm] = useState<'confirm' | 'approve' | 'delete' | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState<string | null>(null);
  const [creatingDelivery, setCreatingDelivery] = useState(false);
  const confirmOrder = salesOrders.useAction('confirm', { success: 'Order confirmed' });
  const approve = salesOrders.useAction('approve', { success: 'Order approved' });
  const cancel = salesOrders.useAction<{ reason: string }>('cancel', { success: 'Order cancelled' });
  const del = salesOrders.useDelete({ onSuccess: () => router.replace('/sales/orders') });
  const details = useDeliveryDetails(o);

  if (editing && o.status === 'draft') {
    return (
      <div className={styles.stack}>
        <PageHeader title={`Edit ${o.number}`} backHref="/sales/orders" backLabel="Sales orders" />
        <OrderEditor order={o} onDone={() => setEditing(false)} />
      </div>
    );
  }

  const run = async (fn: () => Promise<unknown>) => {
    try {
      await fn();
    } catch {
      // toast shown by resource hook
    }
    setConfirm(null);
  };
  const acceptsDeliveries = o.status === 'approved' || o.status === 'partially_delivered';
  const cancellable = ['draft', 'confirmed', 'approved', 'partially_delivered'].includes(o.status);
  const hasDelivered = (o.lines ?? []).some((l) => isPositive(l.quantity_delivered));
  const warehouse = warehouses.rows.find((w) => w.id === o.warehouse_id);
  const s = o.shipping_address;
  const shipLines = [s.name, s.line1, s.line2, [s.city, s.state, s.postal_code].filter(Boolean).join(', '), s.country].filter(Boolean) as string[];

  return (
    <div className={styles.stack}>
      <PageHeader
        title={o.number}
        description={o.customer ? <Link href={`/sales/customers/${o.customer_id}`}>{o.customer.name}</Link> : undefined}
        backHref="/sales/orders"
        backLabel="Sales orders"
        meta={<SalesStatus status={o.status} />}
        actions={
          <>
            {canManage && o.status === 'draft' && (
              <>
                <Button variant="ghost" leftIcon={<Trash2 size={16} />} onClick={() => setConfirm('delete')}>Delete</Button>
                <Button variant="secondary" leftIcon={<Pencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>
              </>
            )}
            {canManage && cancellable && (
              <Button variant="ghost" leftIcon={<Ban size={16} />} disabled={hasDelivered} title={hasDelivered ? 'Orders with delivered quantities cannot be cancelled' : undefined} onClick={() => { setReason(''); setReasonError(null); setCancelling(true); }}>
                Cancel order
              </Button>
            )}
            {canManage && o.status === 'draft' && <Button leftIcon={<ClipboardCheck size={16} />} onClick={() => setConfirm('confirm')} disabled={!o.lines?.length}>Confirm</Button>}
            {can('sales_orders.approve') && o.status === 'confirmed' && <Button leftIcon={<CheckCircle2 size={16} />} onClick={() => setConfirm('approve')}>Approve</Button>}
            {canManage && acceptsDeliveries && <Button leftIcon={<Truck size={16} />} onClick={() => setCreatingDelivery(true)} disabled={details.loading}>Create delivery</Button>}
          </>
        }
      />

      {o.status === 'cancelled' && <Alert variant="warning" title="Cancelled">{o.cancelled_reason ?? 'No reason recorded.'}{o.cancelled_at ? ` · ${formatDateTime(o.cancelled_at)}` : ''}</Alert>}
      {o.status === 'confirmed' && !can('sales_orders.approve') && <Alert variant="info">Waiting for approval by someone with the “Approve sales orders” permission.</Alert>}
      {o.quotation_id && <Alert variant="info">Created from a quotation. <Link href={`/sales/quotations/${o.quotation_id}`}>View quotation →</Link></Alert>}

      <div className={crud.detailGrid}>
        <Card>
          <CardHeader title="Lines" description={o.status === 'draft' ? undefined : 'Read-only once confirmed.'} />
          <CardBody padding="none">
            <DocumentLinesTable lines={o.lines ?? []} currency={o.currency} showDelivered />
            <div style={{ padding: 16 }}>
              <TotalsBlock currency={o.currency} subtotal={o.subtotal} discount={o.discount_total} tax={o.tax_total} total={o.total} />
            </div>
          </CardBody>
        </Card>
        <div className={styles.stack}>
          <Card>
            <CardHeader title="Details" />
            <CardBody>
              <dl className={crud.dl}>
                <dt>Customer</dt><dd>{o.customer ? <Link href={`/sales/customers/${o.customer_id}`}>{o.customer.name}</Link> : '—'}</dd>
                <dt>Order date</dt><dd>{formatDate(o.order_date)}</dd>
                <dt>Expected delivery</dt><dd>{formatDate(o.expected_delivery_date)}</dd>
                <dt>Warehouse</dt><dd>{o.warehouse_id ? (warehouse ? `${warehouse.name} · ${warehouse.code}` : `#${o.warehouse_id}`) : '—'}</dd>
                <dt>Currency</dt><dd>{o.currency}</dd>
                <dt>Sales rep</dt><dd>{o.sales_rep?.name ?? '—'}</dd>
                <dt>Confirmed</dt><dd>{o.confirmed_at ? formatDateTime(o.confirmed_at) : '—'}</dd>
                <dt>Approved</dt><dd>{o.approved_at ? `${formatDateTime(o.approved_at)}${o.approver ? ` by ${o.approver.name}` : ''}` : '—'}</dd>
              </dl>
            </CardBody>
          </Card>
          <Card>
            <CardHeader title="Ship to" />
            <CardBody>
              {shipLines.length ? shipLines.map((l) => <div key={l}>{l}</div>) : <span className={crud.muted}>No shipping address</span>}
            </CardBody>
          </Card>
        </div>
      </div>

      {o.status !== 'draft' && (
        <DeliveriesCard order={o} details={details} canManage={canManage} canCreate={canManage && acceptsDeliveries && !details.loading} onCreate={() => setCreatingDelivery(true)} />
      )}

      {(o.notes || o.terms) && (
        <Card>
          <CardBody>
            <dl className={cn(crud.dl)}>
              {o.notes && (<><dt>Notes</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{o.notes}</dd></>)}
              {o.terms && (<><dt>Terms</dt><dd style={{ whiteSpace: 'pre-wrap' }}>{o.terms}</dd></>)}
            </dl>
          </CardBody>
        </Card>
      )}

      {creatingDelivery && <CreateDeliveryModal order={o} reserved={details.reserved} onClose={() => setCreatingDelivery(false)} />}

      <ConfirmDialog
        open={confirm === 'confirm'}
        onClose={() => setConfirm(null)}
        title={`Confirm ${o.number}?`}
        description="The order becomes read-only and is announced to Inventory and Accounts Receivable. It then needs approval before deliveries."
        confirmLabel="Confirm order"
        onConfirm={() => run(() => confirmOrder.mutateAsync({ id: o.id }))}
      />
      <ConfirmDialog
        open={confirm === 'approve'}
        onClose={() => setConfirm(null)}
        title={`Approve ${o.number}?`}
        description="Approved orders can be delivered and count as won business in sales reports."
        confirmLabel="Approve order"
        onConfirm={() => run(() => approve.mutateAsync({ id: o.id }))}
      />
      <ConfirmDialog
        open={confirm === 'delete'}
        onClose={() => setConfirm(null)}
        title={`Delete draft ${o.number}?`}
        destructive
        confirmLabel="Delete draft"
        onConfirm={() => run(() => del.mutateAsync(o.id))}
      />
      <Modal
        open={cancelling}
        onClose={() => setCancelling(false)}
        size="sm"
        title={`Cancel ${o.number}?`}
        description="Open deliveries are cancelled too. This cannot be undone."
        footer={
          <>
            <Button variant="secondary" onClick={() => setCancelling(false)}>Keep order</Button>
            <Button
              variant="danger"
              loading={cancel.isPending}
              onClick={async () => {
                if (!reason.trim()) return setReasonError('A reason is required');
                try {
                  await cancel.mutateAsync({ id: o.id, body: { reason: reason.trim() } });
                  setCancelling(false);
                } catch (e) {
                  const err = e as ApiError;
                  setReasonError(err.errors?.reason?.[0] ?? err.message);
                }
              }}
            >
              Cancel order
            </Button>
          </>
        }
      >
        <FormField label="Reason" required error={reasonError ?? undefined}>
          <Textarea rows={3} maxLength={500} value={reason} onChange={(e) => setReason(e.target.value)} autoFocus />
        </FormField>
      </Modal>
    </div>
  );
}

function OrderPageInner({ id }: { id: string }) {
  const can = useCan();
  const search = useSearchParams();
  const isNew = id === 'new';
  const q = salesOrders.useOne(isNew ? undefined : id);

  if (isNew) {
    if (!can('sales_orders.manage')) return <Alert variant="warning">You don’t have permission to create sales orders.</Alert>;
    return (
      <div className={styles.stack}>
        <PageHeader title="New sales order" backHref="/sales/orders" backLabel="Sales orders" />
        <OrderEditor order={null} initialCustomerId={search.get('customer_id') ?? undefined} onDone={() => undefined} />
      </div>
    );
  }
  if (q.error) return <ErrorState error={q.error} onRetry={() => void q.refetch()} />;
  if (!q.data) return <SkeletonCard lines={6} />;
  return <OrderDetail key={q.data.id} o={q.data} />;
}

export default function SalesOrderPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  return (
    <Suspense fallback={<SkeletonCard lines={6} />}>
      <OrderPageInner id={id} />
    </Suspense>
  );
}
