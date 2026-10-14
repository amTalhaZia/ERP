'use client';

import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Package, Plus, X } from 'lucide-react';
import { Button, CurrencyInput, IconButton, Input } from '@/components/ui';
import { useDebounce } from '@/hooks/useDebounce';
import { cn } from '@/lib/cn';
import { formatMoney } from '@/lib/format';
import { useProductSearch, type ProductOption } from './api';
import { documentAmounts, fromUnits, sanitizeDecimal, toUnits } from './decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from './procurement.module.scss';

export interface DraftLine {
  key: number;
  product_id: number | null;
  sku: string | null;
  description: string;
  quantity: string;
  uom: string;
  unit_price: string;
  discount_percent: string;
  tax_rate: string;
}

let seq = 0;
export const nextLineKey = () => ++seq;

export function newLine(patch: Partial<DraftLine> = {}): DraftLine {
  return { key: nextLineKey(), product_id: null, sku: null, description: '', quantity: '', uom: '', unit_price: '', discount_percent: '', tax_rate: '', ...patch };
}

const isBlank = (l: DraftLine) => !l.product_id && !l.description.trim() && !l.quantity && !l.unit_price;

/** Drop untouched rows; return the rows to send plus their keys (payload index → row key). */
export function collectLines(lines: DraftLine[]): { rows: DraftLine[]; keys: number[] } {
  const rows = lines.filter((l) => !isBlank(l));
  return { rows, keys: rows.map((l) => l.key) };
}

/** Normalise a server decimal for an editable text input ("10.0000" → "10"). */
export const editable = (v: string | null | undefined) => {
  if (v === null || v === undefined || v === '') return '';
  const s = fromUnits(toUnits(v));
  return s.replace(/\.?0+$/, '') || '0';
};

// ---- Product picker ------------------------------------------------------------

function ProductPicker({
  value,
  sku,
  onText,
  onPick,
  invalid,
  label,
  disabled,
}: {
  value: string;
  sku: string | null;
  onText: (text: string) => void;
  onPick: (p: ProductOption) => void;
  invalid?: boolean;
  label: string;
  disabled?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [rect, setRect] = useState<{ left: number; top: number; width: number } | null>(null);
  const [active, setActive] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const listRef = useRef<HTMLUListElement>(null);
  const term = useDebounce(value.trim(), 300);
  const q = useProductSearch(term, open && term.length >= 1);
  const results = q.data ?? [];

  useEffect(() => {
    if (!open) return;
    const close = (e: Event) => {
      if (listRef.current && e.target instanceof Node && listRef.current.contains(e.target)) return;
      setOpen(false);
    };
    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    return () => {
      window.removeEventListener('scroll', close, true);
      window.removeEventListener('resize', close);
    };
  }, [open]);

  const place = () => {
    const r = inputRef.current?.getBoundingClientRect();
    if (r) setRect({ left: r.left, top: r.bottom + 4, width: Math.max(r.width, 300) });
  };

  const pick = (p: ProductOption) => {
    onPick(p);
    setOpen(false);
  };

  return (
    <div className={styles.picker}>
      <Input
        ref={inputRef}
        aria-label={label}
        value={value}
        disabled={disabled}
        invalid={invalid}
        placeholder="Search product or type a description"
        leading={sku ? <Package size={14} aria-hidden /> : undefined}
        role="combobox"
        aria-expanded={open}
        aria-autocomplete="list"
        onChange={(e) => {
          onText(e.target.value);
          setActive(0);
          place();
          setOpen(true);
        }}
        onFocus={() => {
          if (value.trim()) {
            place();
            setOpen(true);
          }
        }}
        onBlur={() => setOpen(false)}
        onKeyDown={(e) => {
          if (!open || results.length === 0) {
            if (e.key === 'Escape') setOpen(false);
            return;
          }
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => (a + 1) % results.length);
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => (a - 1 + results.length) % results.length);
          } else if (e.key === 'Enter') {
            e.preventDefault();
            const p = results[active];
            if (p) pick(p);
          } else if (e.key === 'Escape') {
            setOpen(false);
          }
        }}
      />
      {sku && <span className={styles.linked}>Linked to {sku}</span>}
      {open && rect && term.length >= 1 &&
        createPortal(
          <ul ref={listRef} className={styles.pickerList} style={{ left: rect.left, top: rect.top, width: rect.width }} role="listbox" aria-label="Products">
            {q.isLoading ? (
              <li className={styles.pickerEmpty}>Searching…</li>
            ) : q.isError ? (
              <li className={styles.pickerEmpty}>Product catalogue unavailable — the text will be used as a free description.</li>
            ) : results.length === 0 ? (
              <li className={styles.pickerEmpty}>No products match. The text will be used as a free description.</li>
            ) : (
              results.map((p, i) => (
                <li key={p.id} role="option" aria-selected={i === active}>
                  <button
                    type="button"
                    className={styles.pickerItem}
                    data-active={i === active}
                    onMouseDown={(e) => e.preventDefault()}
                    onClick={() => pick(p)}
                  >
                    <strong>{p.name}</strong>
                    <span>
                      {p.sku}
                      {p.uom?.symbol ? ` · ${p.uom.symbol}` : ''}
                      {p.cost_price ? ` · cost ${editable(p.cost_price)}` : ''}
                    </span>
                  </button>
                </li>
              ))
            )}
          </ul>,
          document.body,
        )}
    </div>
  );
}

// ---- Lines editor ------------------------------------------------------------

/**
 * Shared lines editor for requisitions (qty × estimated price) and purchase
 * orders (qty × price − discount% + tax%). Totals mirror DocumentTotals with
 * exact BigInt math; the server recomputes and is authoritative.
 */
export function LinesEditor({
  mode,
  lines,
  onChange,
  currency,
  rowErrors = {},
  linesError,
  shipping,
  onShippingChange,
}: {
  mode: 'requisition' | 'order';
  lines: DraftLine[];
  onChange: (lines: DraftLine[]) => void;
  currency: string;
  rowErrors?: Record<number, Record<string, string>>;
  linesError?: string;
  shipping?: string;
  onShippingChange?: (v: string) => void;
}) {
  const isOrder = mode === 'order';
  const priceField = isOrder ? 'unit_price' : 'estimated_unit_price';
  const set = (key: number, patch: Partial<DraftLine>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const amounts = documentAmounts(
    lines.map((l) => ({ quantity: l.quantity, unit_price: l.unit_price, discount_percent: isOrder ? l.discount_percent : '0', tax_rate: isOrder ? l.tax_rate : '0' })),
    isOrder ? shipping ?? '0' : '0',
  );
  const money = (n: bigint) => formatMoney(fromUnits(n), currency);

  return (
    <div className={styles.stack}>
      <div className={crud.tableScroll}>
        <table className={crud.lines} style={{ minWidth: isOrder ? 980 : 760 }}>
          <thead>
            <tr>
              <th style={{ width: 36 }}>#</th>
              <th>Item / description</th>
              <th className={crud.r} style={{ width: 110 }}>Qty</th>
              <th style={{ width: 90 }}>UoM</th>
              <th className={crud.r} style={{ width: 150 }}>{isOrder ? 'Unit price' : 'Est. unit price'}</th>
              {isOrder && <th className={crud.r} style={{ width: 90 }}>Disc. %</th>}
              {isOrder && <th className={crud.r} style={{ width: 90 }}>Tax %</th>}
              <th className={crud.r} style={{ width: 130 }}>Line total</th>
              <th style={{ width: 44 }} aria-label="Remove" />
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => {
              const err = rowErrors[l.key] ?? {};
              const hasErr = Object.keys(err).length > 0;
              const total = amounts.lines[i]?.total ?? 0n;
              return (
                <tr key={l.key} className={hasErr ? styles.rowInvalid : undefined}>
                  <td className={crud.muted}>{i + 1}</td>
                  <td>
                    <ProductPicker
                      label={`Line ${i + 1} item`}
                      value={l.description}
                      sku={l.sku}
                      invalid={!!(err.description || err.product_id)}
                      onText={(text) => set(l.key, { description: text, product_id: null, sku: null })}
                      onPick={(p) =>
                        set(l.key, {
                          product_id: p.id,
                          sku: p.sku,
                          description: p.name,
                          uom: p.uom?.symbol ?? l.uom,
                          unit_price: p.cost_price ? editable(p.cost_price) : l.unit_price,
                          quantity: l.quantity || '1',
                        })
                      }
                    />
                    {(err.description || err.product_id) && <span className={styles.cellError}>{err.description ?? err.product_id}</span>}
                  </td>
                  <td>
                    <Input
                      aria-label={`Line ${i + 1} quantity`}
                      inputMode="decimal"
                      className={styles.narrowInput}
                      style={{ textAlign: 'right' }}
                      value={l.quantity}
                      invalid={!!err.quantity}
                      onChange={(e) => set(l.key, { quantity: sanitizeDecimal(e.target.value, 4) })}
                    />
                    {err.quantity && <span className={styles.cellError}>{err.quantity}</span>}
                  </td>
                  <td>
                    <Input aria-label={`Line ${i + 1} unit of measure`} value={l.uom} maxLength={20} invalid={!!err.uom} onChange={(e) => set(l.key, { uom: e.target.value })} />
                  </td>
                  <td>
                    <CurrencyInput
                      aria-label={`Line ${i + 1} unit price`}
                      currency={currency}
                      value={l.unit_price || null}
                      invalid={!!err[priceField]}
                      onValueChange={(v) => set(l.key, { unit_price: v ?? '' })}
                    />
                    {err[priceField] && <span className={styles.cellError}>{err[priceField]}</span>}
                  </td>
                  {isOrder && (
                    <td>
                      <Input
                        aria-label={`Line ${i + 1} discount percent`}
                        inputMode="decimal"
                        style={{ textAlign: 'right' }}
                        value={l.discount_percent}
                        invalid={!!err.discount_percent}
                        onChange={(e) => set(l.key, { discount_percent: sanitizeDecimal(e.target.value, 2) })}
                      />
                      {err.discount_percent && <span className={styles.cellError}>{err.discount_percent}</span>}
                    </td>
                  )}
                  {isOrder && (
                    <td>
                      <Input
                        aria-label={`Line ${i + 1} tax rate`}
                        inputMode="decimal"
                        style={{ textAlign: 'right' }}
                        value={l.tax_rate}
                        invalid={!!err.tax_rate}
                        onChange={(e) => set(l.key, { tax_rate: sanitizeDecimal(e.target.value, 4) })}
                      />
                      {err.tax_rate && <span className={styles.cellError}>{err.tax_rate}</span>}
                    </td>
                  )}
                  <td className={crud.r} style={{ paddingTop: 14 }}>{money(total)}</td>
                  <td>
                    <IconButton
                      icon={<X size={16} />}
                      aria-label={`Remove line ${i + 1}`}
                      size="sm"
                      disabled={lines.length <= 1}
                      onClick={() => onChange(lines.filter((x) => x.key !== l.key))}
                    />
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={isOrder ? 9 : 7}>
                <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => onChange([...lines, newLine()])}>
                  Add line
                </Button>
                {linesError && <span className={styles.cellError}>{linesError}</span>}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div className={crud.totals}>
        {isOrder ? (
          <>
            <span>Gross</span>
            <span>{money(amounts.subtotal + amounts.discount)}</span>
            <span>Discount</span>
            <span>{amounts.discount > 0n ? `−${money(amounts.discount)}` : money(0n)}</span>
            <span>Subtotal</span>
            <span>{money(amounts.subtotal)}</span>
            <span>Tax</span>
            <span>{money(amounts.tax)}</span>
            <span>Shipping</span>
            <span>
              {onShippingChange ? (
                <CurrencyInput aria-label="Shipping cost" currency={currency} size="sm" value={shipping || null} onValueChange={(v) => onShippingChange(v ?? '')} />
              ) : (
                money(amounts.shipping)
              )}
            </span>
            <span className={cn(crud.grand)}>Total</span>
            <span className={cn(crud.grand)}>{money(amounts.total)}</span>
          </>
        ) : (
          <>
            <span className={crud.grand}>Estimated total</span>
            <span className={crud.grand}>{money(amounts.total)}</span>
          </>
        )}
      </div>
    </div>
  );
}
