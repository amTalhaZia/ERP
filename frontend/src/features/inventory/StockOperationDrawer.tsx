'use client';

import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Plus, X } from 'lucide-react';
import { Alert, Button, CurrencyInput, DateInput, Drawer, FormField, FormGrid, IconButton, Input, Select, Textarea, useToast } from '@/components/ui';
import type { ApiError } from '@/lib/api/errors';
import { stockApi, isInsufficientStock, type InventoryApiError, type IssueLineInput, type Product, type ReceiveLineInput } from './api';
import { InsufficientStockAlert, ProductPicker, QuantityInput, useCompanyId, useCurrency, useInvalidateStock, useWarehouseOptions } from './shared';
import { isPositive, toScaled } from './decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from './inventory.module.scss';

export type StockOperation = 'receive' | 'issue';

interface OpLine {
  key: number;
  product: Product | null;
  quantity: string;
  unit_cost: string | null;
  serials: string;
  batch_number: string;
  expires_at: string;
}

let seq = 0;
const blank = (): OpLine => ({ key: ++seq, product: null, quantity: '', unit_cost: null, serials: '', batch_number: '', expires_at: '' });
const parseSerials = (text: string) => text.split(/[\s,;]+/).map((s) => s.trim()).filter(Boolean);

/**
 * Receive or issue stock into/out of one warehouse. Remount with a `key` each
 * time it opens so state starts fresh.
 */
export function StockOperationDrawer({ open, mode, onClose, defaultProduct }: { open: boolean; mode: StockOperation; onClose: () => void; defaultProduct?: Product | null }) {
  const companyId = useCompanyId();
  const currency = useCurrency();
  const toast = useToast();
  const invalidate = useInvalidateStock();
  const wh = useWarehouseOptions({ activeOnly: true, enabled: open });
  const [warehouseId, setWarehouseId] = useState('');
  const [locationId, setLocationId] = useState('');
  const [reason, setReason] = useState('');
  const [movedAt, setMovedAt] = useState(new Date().toISOString().slice(0, 10));
  const [lines, setLines] = useState<OpLine[]>(() => [
    defaultProduct ? { ...blank(), product: defaultProduct, unit_cost: mode === 'receive' ? defaultProduct.cost_price : null } : blank(),
  ]);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [conflict, setConflict] = useState<InventoryApiError | null>(null);
  const [saving, setSaving] = useState(false);

  // Default warehouse is derived (not synced via effect) until the user picks one.
  const effectiveWarehouse = warehouseId || (wh.defaultId ? String(wh.defaultId) : '');
  const locs = useQuery({
    queryKey: [companyId, 'locations', 'warehouse', effectiveWarehouse, 'active'],
    queryFn: () => stockApi.warehouseLocations(effectiveWarehouse, { per_page: 100, 'filter[is_active]': 'true' }).then((r) => r.data),
    enabled: open && !!effectiveWarehouse,
  });

  const setLine = (key: number, patch: Partial<OpLine>) => setLines((ls) => ls.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const receiving = mode === 'receive';

  const submit = async () => {
    setError(null);
    setFieldErrors({});
    setConflict(null);
    const filled = lines.filter((l) => l.product);
    if (!effectiveWarehouse) return setError('Choose a warehouse.');
    if (filled.length === 0) return setError('Add at least one product line.');
    const bad = filled.findIndex((l) => !isPositive(l.quantity));
    if (bad >= 0) return setError(`Line ${bad + 1}: quantity must be greater than zero.`);
    for (const [i, l] of filled.entries()) {
      if (l.product?.track_serials) {
        const n = parseSerials(l.serials).length;
        if (BigInt(n) * 10000n !== toScaled(l.quantity)) return setError(`Line ${i + 1} (${l.product.sku}) is serial-tracked: enter exactly one serial per unit (${n} entered).`);
      }
    }

    const base = {
      warehouse_id: Number(effectiveWarehouse),
      location_id: locationId ? Number(locationId) : null,
      reason: reason || null,
      moved_at: movedAt || null,
    };
    setSaving(true);
    try {
      if (receiving) {
        const body = {
          ...base,
          lines: filled.map<ReceiveLineInput>((l) => ({
            product_id: l.product!.id,
            quantity: l.quantity,
            unit_cost: l.unit_cost ?? '0',
            batch_number: l.product!.track_batches ? l.batch_number || null : null,
            expires_at: l.product!.track_batches ? l.expires_at || null : null,
            serials: l.product!.track_serials ? parseSerials(l.serials) : null,
          })),
        };
        await stockApi.receive(body);
      } else {
        const body = {
          ...base,
          lines: filled.map<IssueLineInput>((l) => ({
            product_id: l.product!.id,
            quantity: l.quantity,
            serials: l.product!.track_serials ? parseSerials(l.serials) : null,
          })),
        };
        await stockApi.issue(body);
      }
      void invalidate();
      toast.success(receiving ? 'Stock received' : 'Stock issued');
      onClose();
    } catch (e) {
      const err = e as InventoryApiError;
      if (isInsufficientStock(err)) {
        setConflict(err);
      } else if (err.status === 422 && err.errors) {
        const fe: Record<string, string> = {};
        for (const [k, v] of Object.entries(err.errors)) fe[k] = v[0] ?? '';
        setFieldErrors(fe);
        // Show non-field errors (e.g. `serials`, which carries no line index) prominently.
        setError(Object.entries(fe).filter(([k]) => !/^lines\.\d+\./.test(k)).map(([, v]) => v)[0] ?? 'Please correct the highlighted lines.');
      } else {
        setError((err as ApiError).message);
      }
    } finally {
      setSaving(false);
    }
  };

  // Map server line indexes (over the filtered list) back to rendered rows.
  const filledKeys = lines.filter((l) => l.product).map((l) => l.key);
  const lineError = (key: number, field: string) => {
    const idx = filledKeys.indexOf(key);
    return idx >= 0 ? fieldErrors[`lines.${idx}.${field}`] : undefined;
  };
  const conflictKey = conflict?.details ? lines.find((l) => l.product?.id === conflict.details?.product_id)?.key : undefined;

  return (
    <Drawer
      open={open}
      onClose={onClose}
      size="xl"
      title={receiving ? 'Receive stock' : 'Issue stock'}
      description={receiving ? 'Book goods into a warehouse at their unit cost. Average cost is recalculated.' : 'Remove goods from a warehouse (consumption, samples, write-offs).'}
      footer={
        <>
          <Button variant="secondary" onClick={onClose}>Cancel</Button>
          <Button onClick={submit} loading={saving}>{receiving ? 'Receive stock' : 'Issue stock'}</Button>
        </>
      }
    >
      {conflict?.details && <InsufficientStockAlert details={conflict.details} message={conflict.message} />}
      {error && <Alert variant="danger" style={{ marginBottom: 16 }}>{error}</Alert>}
      <FormGrid columns={2}>
        <FormField label="Warehouse" required error={fieldErrors.warehouse_id}>
          <Select
            value={effectiveWarehouse}
            placeholder={wh.query.isLoading ? 'Loading…' : 'Select warehouse'}
            options={wh.options}
            onChange={(e) => {
              setWarehouseId(e.target.value);
              setLocationId('');
            }}
          />
        </FormField>
        <FormField label="Storage location" error={fieldErrors.location_id} hint={locs.data?.length ? undefined : 'No locations defined'}>
          <Select
            value={locationId}
            placeholder="Warehouse level"
            disabled={!locs.data?.length}
            options={(locs.data ?? []).map((l) => ({ value: String(l.id), label: `${l.code} · ${l.name}` }))}
            onChange={(e) => setLocationId(e.target.value)}
          />
        </FormField>
        <FormField label="Date" error={fieldErrors.moved_at}>
          <DateInput value={movedAt} onValueChange={(v) => setMovedAt(v ?? '')} />
        </FormField>
        <FormField label="Reason / reference" error={fieldErrors.reason}>
          <Input value={reason} maxLength={200} onChange={(e) => setReason(e.target.value)} placeholder={receiving ? 'e.g. Opening stock, supplier delivery' : 'e.g. Internal consumption'} />
        </FormField>
      </FormGrid>

      <div className={crud.tableScroll} style={{ marginTop: 20 }}>
        <table className={crud.lines} style={{ minWidth: receiving ? 760 : 600 }}>
          <thead>
            <tr>
              <th style={{ width: receiving ? '48%' : '64%' }}>Product</th>
              <th className={crud.r} style={{ width: 140 }}>Quantity</th>
              {receiving && <th className={crud.r} style={{ width: 170 }}>Unit cost</th>}
              <th style={{ width: 44 }} aria-label="Remove" />
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => (
              <tr key={l.key} className={l.key === conflictKey ? styles.invalidRow : undefined}>
                <td>
                  <ProductPicker
                    aria-label={`Line ${i + 1} product`}
                    value={l.product}
                    invalid={!!lineError(l.key, 'product_id')}
                    onChange={(p) => setLine(l.key, { product: p, unit_cost: receiving && p ? (l.unit_cost ?? p.cost_price) : l.unit_cost })}
                  />
                  {lineError(l.key, 'product_id') && <small style={{ color: 'var(--color-danger-text)' }}>{lineError(l.key, 'product_id')}</small>}
                  {l.product && (l.product.track_serials || (receiving && l.product.track_batches)) && (
                    <div className={styles.lineExtras}>
                      {receiving && l.product.track_batches && (
                        <>
                          <Input aria-label={`Line ${i + 1} batch number`} placeholder="Batch number" maxLength={60} value={l.batch_number} onChange={(e) => setLine(l.key, { batch_number: e.target.value })} invalid={!!lineError(l.key, 'batch_number')} />
                          <DateInput aria-label={`Line ${i + 1} expiry date`} value={l.expires_at} onValueChange={(v) => setLine(l.key, { expires_at: v ?? '' })} invalid={!!lineError(l.key, 'expires_at')} />
                        </>
                      )}
                      {l.product.track_serials && (
                        <div style={{ gridColumn: '1 / -1' }}>
                          <Textarea
                            aria-label={`Line ${i + 1} serial numbers`}
                            rows={2}
                            placeholder="Serial numbers — one per unit, separated by new lines or commas"
                            value={l.serials}
                            onChange={(e) => setLine(l.key, { serials: e.target.value })}
                          />
                          <small className={crud.muted}>{parseSerials(l.serials).length} serial(s)</small>
                        </div>
                      )}
                    </div>
                  )}
                </td>
                <td>
                  <QuantityInput
                    aria-label={`Line ${i + 1} quantity`}
                    value={l.quantity}
                    onValueChange={(v) => setLine(l.key, { quantity: v })}
                    invalid={!!lineError(l.key, 'quantity') || l.key === conflictKey}
                    trailing={l.product?.uom?.symbol}
                    placeholder="0"
                    style={{ textAlign: 'right' }}
                  />
                </td>
                {receiving && (
                  <td>
                    <CurrencyInput aria-label={`Line ${i + 1} unit cost`} currency={currency} value={l.unit_cost} onValueChange={(v) => setLine(l.key, { unit_cost: v })} invalid={!!lineError(l.key, 'unit_cost')} />
                  </td>
                )}
                <td>
                  <IconButton icon={<X size={16} />} aria-label={`Remove line ${i + 1}`} size="sm" disabled={lines.length <= 1} onClick={() => setLines((ls) => ls.filter((x) => x.key !== l.key))} />
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={receiving ? 4 : 3}>
                <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => setLines((ls) => [...ls, blank()])}>Add line</Button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </Drawer>
  );
}
