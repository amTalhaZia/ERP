'use client';

import type { ReactNode } from 'react';
import { Plus, X } from 'lucide-react';
import { Button, IconButton } from '@/components/ui';
import type { Product } from './api';
import { ProductPicker, QuantityInput } from './shared';
import crud from '@/components/crud/crud.module.scss';
import styles from './inventory.module.scss';

export type LineProduct = Pick<Product, 'id' | 'sku' | 'name'> & Partial<Product>;

export interface DraftLine {
  key: number;
  product: LineProduct | null;
  quantity: string;
}

let seq = 0;
export const blankLine = (product: LineProduct | null = null, quantity = ''): DraftLine => ({ key: ++seq, product, quantity });

/**
 * Product + quantity lines editor shared by transfers and adjustments.
 * Server errors are passed as `lines.{i}.{field}` keys indexed over the
 * lines that have a product (the ones actually submitted).
 */
export function LinesEditor({
  lines,
  onChange,
  quantityLabel = 'Quantity',
  quantityField = 'quantity',
  errors = {},
  highlightProductId,
  extraHeader,
  extraCell,
}: {
  lines: DraftLine[];
  onChange: (lines: DraftLine[]) => void;
  quantityLabel?: string;
  quantityField?: string;
  errors?: Record<string, string>;
  highlightProductId?: number;
  extraHeader?: ReactNode;
  extraCell?: (line: DraftLine) => ReactNode;
}) {
  const set = (key: number, patch: Partial<DraftLine>) => onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)));
  const submitted = lines.filter((l) => l.product).map((l) => l.key);
  const err = (key: number, field: string) => {
    const i = submitted.indexOf(key);
    return i >= 0 ? errors[`lines.${i}.${field}`] : undefined;
  };
  const cols = 3 + (extraHeader ? 1 : 0);

  return (
    <div className={crud.tableScroll}>
      <table className={crud.lines} style={{ minWidth: 560 }}>
        <thead>
          <tr>
            <th>Product</th>
            <th className={crud.r} style={{ width: 170 }}>{quantityLabel}</th>
            {extraHeader}
            <th style={{ width: 44 }} aria-label="Remove" />
          </tr>
        </thead>
        <tbody>
          {lines.map((l, i) => {
            const productErr = err(l.key, 'product_id');
            const qtyErr = err(l.key, quantityField);
            const highlighted = highlightProductId !== undefined && l.product?.id === highlightProductId;
            return (
              <tr key={l.key} className={highlighted ? styles.invalidRow : undefined}>
                <td>
                  <ProductPicker aria-label={`Line ${i + 1} product`} value={l.product} invalid={!!productErr} onChange={(p) => set(l.key, { product: p })} />
                  {productErr && <small style={{ color: 'var(--color-danger-text)' }}>{productErr}</small>}
                </td>
                <td>
                  <QuantityInput
                    aria-label={`Line ${i + 1} ${quantityLabel.toLowerCase()}`}
                    value={l.quantity}
                    onValueChange={(v) => set(l.key, { quantity: v })}
                    invalid={!!qtyErr || highlighted}
                    trailing={l.product?.uom?.symbol}
                    placeholder="0"
                  />
                  {qtyErr && <small style={{ color: 'var(--color-danger-text)' }}>{qtyErr}</small>}
                </td>
                {extraCell?.(l)}
                <td>
                  <IconButton icon={<X size={16} />} aria-label={`Remove line ${i + 1}`} size="sm" disabled={lines.length <= 1} onClick={() => onChange(lines.filter((x) => x.key !== l.key))} />
                </td>
              </tr>
            );
          })}
        </tbody>
        <tfoot>
          <tr>
            <td colSpan={cols}>
              <Button size="sm" variant="ghost" leftIcon={<Plus size={14} />} onClick={() => onChange([...lines, blankLine()])}>Add line</Button>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
}

/** Maps a 422 ApiError to `{ field: message }` plus a headline message. */
export function collectErrors(errors: Record<string, string[]> | undefined, fallback: string) {
  const fe: Record<string, string> = {};
  for (const [k, v] of Object.entries(errors ?? {})) fe[k] = v[0] ?? '';
  return { fieldErrors: fe, message: Object.values(fe)[0] ?? fallback };
}
