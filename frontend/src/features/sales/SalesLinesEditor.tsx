'use client';

import { useId, useRef, useState, type KeyboardEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Plus, X } from 'lucide-react';
import { Button, CurrencyInput, IconButton, Input } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { formatMoney } from '@/lib/format';
import { useAppSelector } from '@/store';
import { cn } from '@/lib/cn';
import { salesApi, type DocumentLine, type LineInput, type ProductOption } from './api';
import { documentTotals, formatQty, fromScaled, lineAmounts, sanitizeDecimal, toScaled, trimDecimal } from './decimal';
import crud from '@/components/crud/crud.module.scss';
import styles from './sales.module.scss';

export interface DraftLine {
  key: number;
  product_id: number | null;
  product_label: string | null;
  description: string;
  quantity: string;
  uom: string;
  unit_price: string;
  discount_percent: string;
  tax_rate: string;
}

let seq = 0;
export const blankLine = (): DraftLine => ({
  key: ++seq,
  product_id: null,
  product_label: null,
  description: '',
  quantity: '1',
  uom: '',
  unit_price: '',
  discount_percent: '',
  tax_rate: '',
});

export const linesFromDocument = (lines: DocumentLine[] | undefined): DraftLine[] =>
  lines && lines.length > 0
    ? lines.map((l) => ({
        key: ++seq,
        product_id: l.product_id,
        product_label: l.product_id ? `Product #${l.product_id}` : null,
        description: l.description,
        quantity: trimDecimal(l.quantity),
        uom: l.uom ?? '',
        unit_price: l.unit_price,
        discount_percent: Number(l.discount_percent) ? trimDecimal(l.discount_percent) : '',
        tax_rate: Number(l.tax_rate) ? trimDecimal(l.tax_rate) : '',
      }))
    : [blankLine()];

const isBlank = (l: DraftLine) => !l.description.trim() && !l.product_id && !l.unit_price;

/** Payload rows for the API; skips fully blank rows. Returns the original index map for error mapping. */
export function linesToPayload(lines: DraftLine[]): { payload: LineInput[]; indexMap: number[] } {
  const payload: LineInput[] = [];
  const indexMap: number[] = [];
  lines.forEach((l, i) => {
    if (isBlank(l)) return;
    indexMap.push(i);
    payload.push({
      product_id: l.product_id,
      description: l.description.trim(),
      quantity: l.quantity || '0',
      uom: l.uom.trim() || null,
      unit_price: l.unit_price || '0',
      discount_percent: l.discount_percent || null,
      tax_rate: l.tax_rate || null,
    });
  });
  return { payload, indexMap };
}

/** Re-keys server errors `lines.{payloadIndex}.x` → `lines.{editorIndex}.x`. */
export function remapLineErrors(fields: Record<string, string>, indexMap: number[]): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [k, v] of Object.entries(fields)) {
    const m = /^lines\.(\d+)\.(.+)$/.exec(k);
    if (m) out[`lines.${indexMap[Number(m[1])] ?? m[1]}.${m[2]}`] = v;
    else out[k] = v;
  }
  return out;
}

// ---------------------------------------------------------------------------
// Product combobox (debounced search; free text allowed)
// ---------------------------------------------------------------------------

function ProductCombobox({
  value,
  productLabel,
  invalid,
  label,
  onText,
  onPick,
  onUnlink,
}: {
  value: string;
  productLabel: string | null;
  invalid?: boolean;
  label: string;
  onText: (text: string) => void;
  onPick: (p: ProductOption) => void;
  onUnlink: () => void;
}) {
  const can = useCan();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const listId = useId();
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const [term, setTerm] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const enabled = can('products.view') && open && term.trim().length >= 1;

  const q = useQuery({
    queryKey: [companyId, 'products', 'sales-picker', term.trim()],
    queryFn: () => salesApi.products(term.trim()),
    enabled,
    staleTime: 30_000,
  });
  const results = enabled ? (q.data ?? []) : [];
  const showList = open && enabled;

  const pick = (p: ProductOption) => {
    onPick(p);
    setOpen(false);
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (!showList) {
      if (e.key === 'ArrowDown' && term) setOpen(true);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive((a) => Math.min(a + 1, results.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive((a) => Math.max(a - 1, 0));
    } else if (e.key === 'Enter' && results[active]) {
      e.preventDefault();
      pick(results[active]!);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      setOpen(false);
    }
  };

  return (
    <div className={styles.combo}>
      <Input
        aria-label={label}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={showList}
        aria-controls={showList ? listId : undefined}
        aria-activedescendant={showList && results[active] ? `${listId}-${active}` : undefined}
        invalid={invalid}
        value={value}
        placeholder={can('products.view') ? 'Search products or type a description' : 'Description'}
        maxLength={500}
        autoComplete="off"
        onChange={(e) => {
          const text = e.target.value;
          onText(text);
          if (timer.current) clearTimeout(timer.current);
          timer.current = setTimeout(() => {
            setTerm(text);
            setActive(0);
          }, 300);
          setOpen(true);
        }}
        onBlur={() => setOpen(false)}
        onKeyDown={onKeyDown}
      />
      {showList && (
        <ul id={listId} role="listbox" className={styles.listbox} aria-label="Products">
          {q.isLoading ? (
            <li className={styles.optionEmpty}>Searching…</li>
          ) : results.length === 0 ? (
            <li className={styles.optionEmpty}>No products match — the text will be used as a free-text line.</li>
          ) : (
            results.map((p, i) => (
              <li
                key={p.id}
                id={`${listId}-${i}`}
                role="option"
                aria-selected={i === active}
                className={styles.option}
                onMouseDown={(e) => e.preventDefault()}
                onMouseEnter={() => setActive(i)}
                onClick={() => pick(p)}
              >
                <span style={{ minWidth: 0 }}>
                  <strong>{p.name}</strong>
                  <small>{p.sku}{p.uom?.symbol ? ` · ${p.uom.symbol}` : ''}</small>
                </span>
                <span className={styles.optionPrice}>{p.sale_price}</span>
              </li>
            ))
          )}
        </ul>
      )}
      {productLabel && (
        <div className={styles.comboMeta}>
          <span>{productLabel}</span>
          <button type="button" onClick={onUnlink}>Unlink</button>
        </div>
      )}
    </div>
  );
}

// ---------------------------------------------------------------------------
// Editor
// ---------------------------------------------------------------------------

export function SalesLinesEditor({
  lines,
  onChange,
  currency,
  errors = {},
}: {
  lines: DraftLine[];
  onChange: (next: DraftLine[]) => void;
  currency: string;
  /** Keys like `lines.0.quantity` (editor indices). */
  errors?: Record<string, string>;
}) {
  const setLine = (key: number, patch: Partial<DraftLine>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const totals = documentTotals(lines.map((l) => ({ quantity: l.quantity, unit_price: l.unit_price, discount_percent: l.discount_percent, tax_rate: l.tax_rate })));
  const money = (n: bigint) => formatMoney(fromScaled(n), currency);
  const err = (i: number, f: string) => errors[`lines.${i}.${f}`];

  return (
    <div>
      {errors.lines && <span className={styles.lineError} role="alert">{errors.lines}</span>}
      <div className={crud.tableScroll}>
        <table className={cn(crud.lines, styles.linesTable)}>
          <thead>
            <tr>
              <th aria-label="Line number">#</th>
              <th>Item</th>
              <th className={crud.r} style={{ width: 110 }}>Qty</th>
              <th style={{ width: 84 }}>UoM</th>
              <th className={crud.r} style={{ width: 150 }}>Unit price</th>
              <th className={crud.r} style={{ width: 88 }}>Disc %</th>
              <th className={crud.r} style={{ width: 88 }}>Tax %</th>
              <th className={crud.r} style={{ width: 130 }}>Line total</th>
              <th style={{ width: 44 }} aria-label="Remove" />
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => {
              const a = lineAmounts(l.quantity, l.unit_price, l.discount_percent || null, l.tax_rate || null);
              const rowErrors = ['product_id', 'description', 'quantity', 'uom', 'unit_price', 'discount_percent', 'tax_rate'].map((f) => err(i, f)).filter(Boolean);
              return (
                <tr key={l.key} className={rowErrors.length ? styles.rowError : undefined}>
                  <td className={styles.lineNo}>{i + 1}</td>
                  <td>
                    <ProductCombobox
                      label={`Line ${i + 1} item`}
                      value={l.description}
                      productLabel={l.product_label}
                      invalid={!!err(i, 'description') || !!err(i, 'product_id')}
                      onText={(text) => setLine(l.key, { description: text })}
                      onUnlink={() => setLine(l.key, { product_id: null, product_label: null })}
                      onPick={(p) =>
                        setLine(l.key, {
                          product_id: p.id,
                          product_label: p.sku,
                          description: p.name,
                          uom: p.uom?.symbol ?? l.uom,
                          unit_price: p.sale_price,
                          tax_rate: p.tax_rate && Number(p.tax_rate) ? trimDecimal(p.tax_rate) : '',
                        })
                      }
                    />
                    {rowErrors.map((m) => (
                      <span key={m} className={styles.lineError}>{m}</span>
                    ))}
                  </td>
                  <td>
                    <Input
                      className={styles.qtyInput}
                      aria-label={`Line ${i + 1} quantity`}
                      inputMode="decimal"
                      value={l.quantity}
                      invalid={!!err(i, 'quantity')}
                      onChange={(e) => setLine(l.key, { quantity: sanitizeDecimal(e.target.value) })}
                    />
                  </td>
                  <td>
                    <Input aria-label={`Line ${i + 1} unit of measure`} value={l.uom} maxLength={10} invalid={!!err(i, 'uom')} onChange={(e) => setLine(l.key, { uom: e.target.value })} />
                  </td>
                  <td>
                    <CurrencyInput
                      aria-label={`Line ${i + 1} unit price`}
                      currency={currency}
                      value={l.unit_price || null}
                      invalid={!!err(i, 'unit_price')}
                      onValueChange={(v) => setLine(l.key, { unit_price: v ?? '' })}
                    />
                  </td>
                  <td>
                    <Input
                      className={styles.pctInput}
                      aria-label={`Line ${i + 1} discount percent`}
                      inputMode="decimal"
                      placeholder="0"
                      value={l.discount_percent}
                      invalid={!!err(i, 'discount_percent')}
                      onChange={(e) => setLine(l.key, { discount_percent: sanitizeDecimal(e.target.value, 2) })}
                    />
                  </td>
                  <td>
                    <Input
                      className={styles.pctInput}
                      aria-label={`Line ${i + 1} tax rate percent`}
                      inputMode="decimal"
                      placeholder="0"
                      value={l.tax_rate}
                      invalid={!!err(i, 'tax_rate')}
                      onChange={(e) => setLine(l.key, { tax_rate: sanitizeDecimal(e.target.value) })}
                    />
                  </td>
                  <td className={cn(crud.r, styles.lineTotal)}>{money(a.total)}</td>
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
              <td colSpan={9}>
                <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => onChange([...lines, blankLine()])}>Add line</Button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div className={styles.editorFooter}>
        <p className={styles.previewNote}>Totals are a live preview; the server recalculates them on save.</p>
        <TotalsBlock currency={currency} subtotal={fromScaled(totals.subtotal)} discount={fromScaled(totals.discount)} tax={fromScaled(totals.tax)} total={fromScaled(totals.total)} />
      </div>
    </div>
  );
}

/** Totals summary; `subtotal` is net of discount (backend semantics). */
export function TotalsBlock({ currency, subtotal, discount, tax, total }: { currency: string; subtotal: string; discount: string; tax: string; total: string }) {
  const money = (v: string) => formatMoney(v, currency);
  const hasDiscount = toScaled(discount) !== 0n;
  return (
    <div className={crud.totals} aria-label="Document totals">
      {hasDiscount && (
        <>
          <span>Gross</span>
          <span>{money(fromScaled(toScaled(subtotal) + toScaled(discount)))}</span>
          <span>Discount</span>
          <span>−{money(discount)}</span>
        </>
      )}
      <span>Subtotal</span>
      <span>{money(subtotal)}</span>
      <span>Tax</span>
      <span>{money(tax)}</span>
      <span className={crud.grand}>Total</span>
      <span className={crud.grand}>{money(total)}</span>
    </div>
  );
}

/** Read-only lines table for issued documents. */
export function DocumentLinesTable({ lines, currency, showDelivered }: { lines: DocumentLine[]; currency: string; showDelivered?: boolean }) {
  const money = (v: string) => formatMoney(v, currency);
  return (
    <div className={crud.tableScroll}>
      <table className={crud.lines} style={{ minWidth: showDelivered ? 860 : 760 }}>
        <thead>
          <tr>
            <th>#</th>
            <th>Item</th>
            <th className={crud.r}>Qty</th>
            {showDelivered && <th className={crud.r}>Delivered</th>}
            <th className={crud.r}>Unit price</th>
            <th className={crud.r}>Disc %</th>
            <th className={crud.r}>Tax %</th>
            <th className={crud.r}>Tax</th>
            <th className={crud.r}>Line total</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((l) => (
            <tr key={l.id}>
              <td className={crud.muted}>{l.line_no}</td>
              <td>
                {l.description}
                {l.product_id ? <div className={crud.muted} style={{ fontSize: 12 }}>Product #{l.product_id}</div> : null}
              </td>
              <td className={crud.r}>{formatQty(l.quantity)} {l.uom ?? ''}</td>
              {showDelivered && <td className={crud.r}>{formatQty(l.quantity_delivered ?? '0')}</td>}
              <td className={crud.r}>{money(l.unit_price)}</td>
              <td className={crud.r}>{Number(l.discount_percent) ? trimDecimal(l.discount_percent) : '—'}</td>
              <td className={crud.r}>{Number(l.tax_rate) ? trimDecimal(l.tax_rate) : '—'}</td>
              <td className={crud.r}>{money(l.tax_amount)}</td>
              <td className={crud.r}>{money(l.line_total)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
