'use client';

import { Package, Plus, X } from 'lucide-react';
import { Button, IconButton, Input, Select, Tooltip } from '@/components/ui';
import { formatMoney } from '@/lib/format';
import { ProductPicker } from '@/features/inventory/shared';
import type { Product } from '@/features/inventory/api';
import type { DocumentLine, DocumentLineInput, TaxRateFull } from './api';
import { centsToDecimal, lineAmounts, sanitizeDecimal, sumTotals, trimDecimal, type LineAmounts } from './money';
import crud from '@/components/crud/crud.module.scss';
import styles from './receivables.module.scss';

type PickedProduct = Pick<Product, 'id' | 'sku' | 'name'> & Partial<Product>;

export interface DraftDocLine {
  key: number;
  product: PickedProduct | null;
  showProduct: boolean;
  description: string;
  quantity: string;
  unit_price: string;
  discount_percent: string;
  tax_rate_id: string;
  account_id: string;
}

let seq = 0;
export const blankDocLine = (): DraftDocLine => ({
  key: ++seq,
  product: null,
  showProduct: false,
  description: '',
  quantity: '1',
  unit_price: '',
  discount_percent: '',
  tax_rate_id: '',
  account_id: '',
});

export function draftFromLine(l: DocumentLine): DraftDocLine {
  return {
    key: ++seq,
    product: l.product_id ? { id: l.product_id, sku: `#${l.product_id}`, name: l.description } : null,
    showProduct: !!l.product_id,
    description: l.description,
    quantity: trimDecimal(l.quantity),
    unit_price: trimDecimal(l.unit_price),
    discount_percent: Number(l.discount_percent) ? trimDecimal(l.discount_percent) : '',
    tax_rate_id: l.tax_rate_id ? String(l.tax_rate_id) : '',
    account_id: l.account_id ? String(l.account_id) : '',
  };
}

/** A line the user has not touched (ignored on submit). */
export const isBlankDocLine = (l: DraftDocLine) => !l.product && !l.description.trim() && !l.unit_price;

export const docLineToInput = (l: DraftDocLine): DocumentLineInput => ({
  product_id: l.product?.id ?? null,
  description: l.description.trim(),
  quantity: l.quantity || '0',
  unit_price: l.unit_price || '0',
  discount_percent: l.discount_percent || '0',
  tax_rate_id: l.tax_rate_id ? Number(l.tax_rate_id) : null,
  account_id: l.account_id ? Number(l.account_id) : null,
});

export function computeLines(lines: DraftDocLine[], taxById: Map<number, TaxRateFull>): LineAmounts[] {
  return lines.map((l) => lineAmounts(l.quantity, l.unit_price, l.discount_percent, l.tax_rate_id ? taxById.get(Number(l.tax_rate_id))?.rate ?? '0' : '0'));
}

/**
 * Invoice / bill lines: optional product, description, qty, unit price,
 * discount %, tax rate, GL account and a live (BigInt) line total.
 * Server errors arrive as `lines.{i}.{field}` indexed over non-blank lines.
 */
export function DocumentLinesEditor({
  lines,
  onChange,
  taxRates,
  accountOptions,
  accountPlaceholder,
  priceField,
  currency,
  errors = {},
}: {
  lines: DraftDocLine[];
  onChange: (lines: DraftDocLine[]) => void;
  taxRates: TaxRateFull[];
  accountOptions: { value: string; label: string }[];
  accountPlaceholder: string;
  priceField: 'sale_price' | 'cost_price';
  currency: string;
  errors?: Record<string, string>;
}) {
  const taxById = new Map(taxRates.map((t) => [t.id, t]));
  const amounts = computeLines(lines, taxById);
  const totals = sumTotals(amounts.filter((_, i) => !isBlankDocLine(lines[i]!)));
  const set = (key: number, patch: Partial<DraftDocLine>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const submitted = lines.filter((l) => !isBlankDocLine(l)).map((l) => l.key);
  const err = (key: number, field: string) => {
    const i = submitted.indexOf(key);
    return i >= 0 ? errors[`lines.${i}.${field}`] : undefined;
  };
  const taxOptions = taxRates.map((t) => ({ value: String(t.id), label: `${t.code} · ${trimDecimal(t.rate) || '0'}%` }));

  return (
    <>
      <div className={crud.tableScroll}>
        <table className={crud.lines} style={{ minWidth: 1080 }}>
          <thead>
            <tr>
              <th style={{ minWidth: 260 }}>Item / description</th>
              <th className={crud.r} style={{ width: 96 }}>Qty</th>
              <th className={crud.r} style={{ width: 130 }}>Unit price</th>
              <th className={crud.r} style={{ width: 84 }}>Disc %</th>
              <th style={{ width: 150 }}>Tax</th>
              <th style={{ width: 200 }}>Account</th>
              <th className={crud.r} style={{ width: 120 }}>Total</th>
              <th style={{ width: 44 }} aria-label="Remove" />
            </tr>
          </thead>
          <tbody>
            {lines.map((l, i) => {
              const a = amounts[i]!;
              const rowErr = ['description', 'quantity', 'unit_price', 'discount_percent', 'tax_rate_id', 'account_id', 'product_id'].some((f) => err(l.key, f));
              return (
                <tr key={l.key} className={rowErr ? styles.invalidRow : undefined}>
                  <td>
                    <div className={styles.cellStack}>
                      {l.showProduct && (
                        <ProductPicker
                          aria-label={`Line ${i + 1} product`}
                          goodsOnly={false}
                          value={l.product}
                          invalid={!!err(l.key, 'product_id')}
                          onChange={(p) =>
                            set(l.key, {
                              product: p,
                              description: p && !l.description.trim() ? p.name : l.description,
                              unit_price: p && !l.unit_price ? trimDecimal(p[priceField]) : l.unit_price,
                            })
                          }
                        />
                      )}
                      <div className={styles.inline} style={{ flexWrap: 'nowrap' }}>
                        <Input
                          aria-label={`Line ${i + 1} description`}
                          placeholder="Description"
                          value={l.description}
                          maxLength={255}
                          invalid={!!err(l.key, 'description')}
                          onChange={(e) => set(l.key, { description: e.target.value })}
                        />
                        {!l.showProduct && (
                          <Tooltip content="Pick a product">
                            <IconButton size="sm" icon={<Package size={16} />} aria-label={`Line ${i + 1}: pick a product`} onClick={() => set(l.key, { showProduct: true })} />
                          </Tooltip>
                        )}
                      </div>
                      {(err(l.key, 'description') || err(l.key, 'product_id')) && <small className={styles.fieldError}>{err(l.key, 'description') ?? err(l.key, 'product_id')}</small>}
                    </div>
                  </td>
                  <td>
                    <Input aria-label={`Line ${i + 1} quantity`} inputMode="decimal" value={l.quantity} invalid={!!err(l.key, 'quantity')} onChange={(e) => set(l.key, { quantity: sanitizeDecimal(e.target.value) })} style={{ textAlign: 'right' }} />
                    {err(l.key, 'quantity') && <small className={styles.fieldError}>{err(l.key, 'quantity')}</small>}
                  </td>
                  <td>
                    <Input aria-label={`Line ${i + 1} unit price`} inputMode="decimal" placeholder="0.00" value={l.unit_price} invalid={!!err(l.key, 'unit_price')} onChange={(e) => set(l.key, { unit_price: sanitizeDecimal(e.target.value) })} style={{ textAlign: 'right' }} />
                    {err(l.key, 'unit_price') && <small className={styles.fieldError}>{err(l.key, 'unit_price')}</small>}
                  </td>
                  <td>
                    <Input
                      aria-label={`Line ${i + 1} discount percent`}
                      inputMode="decimal"
                      placeholder="0"
                      value={l.discount_percent}
                      invalid={!!err(l.key, 'discount_percent')}
                      onChange={(e) => {
                        const v = sanitizeDecimal(e.target.value);
                        set(l.key, { discount_percent: Number(v) > 100 ? '100' : v });
                      }}
                      style={{ textAlign: 'right' }}
                    />
                    {err(l.key, 'discount_percent') && <small className={styles.fieldError}>{err(l.key, 'discount_percent')}</small>}
                  </td>
                  <td>
                    <Select aria-label={`Line ${i + 1} tax rate`} placeholder="No tax" value={l.tax_rate_id} options={taxOptions} invalid={!!err(l.key, 'tax_rate_id')} onChange={(e) => set(l.key, { tax_rate_id: e.target.value })} />
                    {err(l.key, 'tax_rate_id') && <small className={styles.fieldError}>{err(l.key, 'tax_rate_id')}</small>}
                  </td>
                  <td>
                    <Select aria-label={`Line ${i + 1} account`} placeholder={accountPlaceholder} value={l.account_id} options={accountOptions} invalid={!!err(l.key, 'account_id')} onChange={(e) => set(l.key, { account_id: e.target.value })} />
                    {err(l.key, 'account_id') && <small className={styles.fieldError}>{err(l.key, 'account_id')}</small>}
                  </td>
                  <td className={crud.r}>
                    <div className={styles.cellStack} style={{ alignItems: 'flex-end', paddingTop: 8 }}>
                      <span>{formatMoney(centsToDecimal(a.total), currency)}</span>
                      {a.tax > 0n && <span className={styles.hint}>incl. tax {formatMoney(centsToDecimal(a.tax), currency)}</span>}
                    </div>
                  </td>
                  <td>
                    <IconButton icon={<X size={16} />} aria-label={`Remove line ${i + 1}`} size="sm" disabled={lines.length <= 1} onClick={() => onChange(lines.filter((x) => x.key !== l.key))} />
                  </td>
                </tr>
              );
            })}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={8}>
                <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => onChange([...lines, blankDocLine()])}>Add line</Button>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div className={styles.linesFooter}>
        <span className={styles.hint}>Preview — totals are recalculated by the server (2-decimal rounding per line).</span>
        <DocumentTotals currency={currency} subtotal={centsToDecimal(totals.subtotal)} discount={centsToDecimal(totals.discount)} tax={centsToDecimal(totals.tax)} total={centsToDecimal(totals.total)} />
      </div>
    </>
  );
}

export function DocumentTotals({ currency, subtotal, discount, tax, total, paid, balance }: { currency: string; subtotal: string; discount?: string; tax: string; total: string; paid?: string; balance?: string }) {
  return (
    <div className={crud.totals}>
      {discount !== undefined && Number(discount) !== 0 && (
        <>
          <span>Discount</span>
          <span>−{formatMoney(discount, currency)}</span>
        </>
      )}
      <span>Subtotal</span>
      <span>{formatMoney(subtotal, currency)}</span>
      <span>Tax</span>
      <span>{formatMoney(tax, currency)}</span>
      <span className={crud.grand}>Total</span>
      <span className={crud.grand}>{formatMoney(total, currency)}</span>
      {paid !== undefined && (
        <>
          <span>Paid</span>
          <span>{formatMoney(paid, currency)}</span>
        </>
      )}
      {balance !== undefined && (
        <>
          <span>Balance due</span>
          <span>{formatMoney(balance, currency)}</span>
        </>
      )}
    </div>
  );
}
