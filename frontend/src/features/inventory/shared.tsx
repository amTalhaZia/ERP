'use client';

import { useId, useState, type ReactNode } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Search, X } from 'lucide-react';
import { Alert, IconButton, Input, type InputProps } from '@/components/ui';
import { useDebounce } from '@/hooks/useDebounce';
import { get } from '@/lib/api/client';
import type { Paginated } from '@/lib/api/types';
import { useAppSelector } from '@/store';
import { formatQty, sanitizeQty } from './decimal';
import { isStockKey, warehouses, type InsufficientStockDetails, type Product, type Warehouse } from './api';
import styles from './inventory.module.scss';

export const useCompanyId = () => useAppSelector((s) => s.session.current_company?.id ?? 0);
export const useCurrency = () => useAppSelector((s) => s.session.current_company?.currency ?? 'USD');

/** Invalidate every cached inventory query after a stock-changing operation. */
export function useInvalidateStock() {
  const qc = useQueryClient();
  return () => qc.invalidateQueries({ predicate: (q) => isStockKey(q.queryKey) });
}

/** Active warehouses as Select options (max page size; warehouses are few). */
export function useWarehouseOptions(opts: { activeOnly?: boolean; enabled?: boolean } = {}) {
  const q = warehouses.useList(
    { per_page: 100, sort: 'name', ...(opts.activeOnly ? { 'filter[is_active]': 'true' } : {}) },
    { enabled: opts.enabled ?? true },
  );
  const rows: Warehouse[] = q.data?.data ?? [];
  return {
    query: q,
    rows,
    defaultId: rows.find((w) => w.is_default)?.id,
    options: rows.map((w) => ({ value: String(w.id), label: `${w.code} · ${w.name}` })),
  };
}

/** String-valued 4dp decimal input (quantities). Never converts to float. */
export function QuantityInput({
  value,
  onValueChange,
  digits = 4,
  ...rest
}: Omit<InputProps, 'value' | 'onChange' | 'type'> & { value: string; onValueChange: (v: string) => void; digits?: number }) {
  return (
    <Input
      {...rest}
      type="text"
      inputMode="decimal"
      autoComplete="off"
      value={value}
      onChange={(e) => onValueChange(sanitizeQty(e.target.value, digits))}
    />
  );
}

type PickerProduct = Pick<Product, 'id' | 'sku' | 'name'> & Partial<Product>;

/**
 * Searchable product combobox backed by `/products?search=`. Only active goods
 * are offered (services hold no stock). Emits the full Product so callers can
 * read track_serials / track_batches / cost_price.
 */
export function ProductPicker({
  value,
  onChange,
  invalid,
  disabled,
  'aria-label': ariaLabel = 'Product',
  goodsOnly = true,
}: {
  value: PickerProduct | null;
  onChange: (p: Product | null) => void;
  invalid?: boolean;
  disabled?: boolean;
  'aria-label'?: string;
  goodsOnly?: boolean;
}) {
  const companyId = useCompanyId();
  const listId = useId();
  const [text, setText] = useState('');
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const term = useDebounce(text, 250);

  const params: Record<string, unknown> = { search: term || undefined, per_page: 20, sort: 'name', 'filter[is_active]': 'true' };
  if (goodsOnly) params['filter[type]'] = 'goods';
  const q = useQuery({
    queryKey: [companyId, 'products', 'picker', params],
    queryFn: () => get<Paginated<Product>>('/products', params).then((r) => r.data),
    enabled: open,
    staleTime: 30_000,
  });
  const results = q.data ?? [];

  const choose = (p: Product) => {
    onChange(p);
    setText('');
    setOpen(false);
  };

  if (value && !open) {
    return (
      <Input
        aria-label={ariaLabel}
        readOnly
        disabled={disabled}
        invalid={invalid}
        value={`${value.sku} · ${value.name}`}
        onFocus={() => !disabled && setOpen(true)}
        onClick={() => !disabled && setOpen(true)}
        trailing={disabled ? undefined : <IconButton size="sm" icon={<X size={14} />} aria-label="Clear product" onClick={() => onChange(null)} />}
      />
    );
  }

  return (
    <div className={styles.picker}>
      <Input
        aria-label={ariaLabel}
        role="combobox"
        aria-expanded={open}
        aria-controls={listId}
        aria-autocomplete="list"
        autoFocus={!!value}
        disabled={disabled}
        invalid={invalid}
        placeholder="Search SKU, name or barcode"
        leading={<Search size={14} />}
        value={text}
        onChange={(e) => {
          setText(e.target.value);
          setActive(0);
          setOpen(true);
        }}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        onKeyDown={(e) => {
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((a) => Math.min(a + 1, results.length - 1));
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((a) => Math.max(a - 1, 0));
          } else if (e.key === 'Enter' && open && results[active]) {
            e.preventDefault();
            choose(results[active]);
          } else if (e.key === 'Escape') {
            setOpen(false);
          }
        }}
      />
      {open && (
        <ul className={styles.pickerList} id={listId} role="listbox">
          {q.isLoading ? (
            <li className={styles.pickerEmpty}>Searching…</li>
          ) : results.length === 0 ? (
            <li className={styles.pickerEmpty}>No matching products</li>
          ) : (
            results.map((p, i) => (
              <li key={p.id} role="option" aria-selected={i === active}>
                <button
                  type="button"
                  className={styles.pickerOption}
                  data-active={i === active || undefined}
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={() => choose(p)}
                >
                  <strong>{p.sku} · {p.name}</strong>
                  <span>
                    On hand {formatQty(p.quantity_on_hand ?? '0')} {p.uom?.symbol ?? ''}
                    {p.track_serials ? ' · serial-tracked' : ''}
                    {p.track_batches ? ' · batch-tracked' : ''}
                  </span>
                </button>
              </li>
            ))
          )}
        </ul>
      )}
    </div>
  );
}

/** Friendly rendering of a 409 inventory.insufficient_stock error. */
export function InsufficientStockAlert({ details, message, productName }: { details: InsufficientStockDetails; message: string; productName?: ReactNode }) {
  return (
    <Alert variant="danger" title="Not enough stock" style={{ marginBottom: 16 }}>
      <div className={styles.conflict}>
        <span>{productName ?? message}</span>
        <ul>
          <li>Product: <strong>{details.sku}</strong></li>
          <li>Available: <strong>{formatQty(details.available)}</strong></li>
          <li>Requested: <strong>{formatQty(details.requested)}</strong></li>
        </ul>
      </div>
    </Alert>
  );
}

/** Signed quantity coloured in/out with success/danger text tokens. */
export function SignedQty({ value }: { value: string }) {
  const neg = value.trim().startsWith('-');
  const zero = /^-?0*(\.0*)?$/.test(value.trim());
  return <span className={zero ? undefined : neg ? styles.out : styles.in}>{formatQty(value, { signed: true })}</span>;
}
