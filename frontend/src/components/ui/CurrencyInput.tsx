'use client';

import { useState, type ChangeEvent, type FocusEvent } from 'react';
import { currencyFractionDigits, currencySymbol, DEFAULT_LOCALE } from '@/lib/format';
import { Input, type InputProps } from './Input';
import styles from './CurrencyInput.module.scss';

export interface CurrencyInputProps extends Omit<InputProps, 'type' | 'value' | 'defaultValue' | 'onChange'> {
  /** Decimal string, e.g. "1234.50". Never a float. */
  value?: string | null;
  defaultValue?: string | null;
  /** Emits a normalised decimal string ("1234.50") or null when empty. */
  onValueChange?: (value: string | null) => void;
  /** ISO 4217 code: "USD", "PKR", "EUR". Drives the symbol and fraction digits. */
  currency: string;
  locale?: string;
  /** What to show in the leading adornment. */
  symbol?: 'symbol' | 'code' | 'none';
  allowNegative?: boolean;
  /** Native change event passthrough. */
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
}

/** Keeps digits, a single leading minus and a single decimal point, truncating to `digits` decimals. */
function sanitize(text: string, digits: number, allowNegative: boolean): string {
  let out = text.replace(/[^\d.-]/g, '');
  out = allowNegative ? out.replace(/(?!^)-/g, '') : out.replace(/-/g, '');
  if (digits === 0) return out.replace(/\./g, '');
  const [head, ...tail] = out.split('.');
  return tail.length > 0 ? `${head}.${tail.join('').slice(0, digits)}` : out;
}

/**
 * String-only normalisation to a fixed number of fraction digits.
 * "12." → "12.00", "-.5" → "-0.50", "" → null. No floating point involved.
 */
export function normalizeDecimal(raw: string | null | undefined, digits: number): string | null {
  if (raw === null || raw === undefined) return null;
  const text = raw.trim();
  if (text === '' || text === '-' || text === '.' || text === '-.') return null;
  const negative = text.startsWith('-');
  const unsigned = negative ? text.slice(1) : text;
  const [intPartRaw = '', fracRaw = ''] = unsigned.split('.');
  const intPart = intPartRaw.replace(/^0+(?=\d)/, '') || '0';
  const frac = digits > 0 ? (fracRaw + '0'.repeat(digits)).slice(0, digits) : '';
  const isZero = /^0*$/.test(intPart) && /^0*$/.test(frac);
  const sign = negative && !isZero ? '-' : '';
  return digits > 0 ? `${sign}${intPart}.${frac}` : `${sign}${intPart}`;
}

/** Display-only grouping of a decimal string: "1234.5" → "1,234.50". */
function formatDisplay(value: string | null, digits: number, locale: string): string {
  if (value === null) return '';
  const n = Number(value);
  if (!Number.isFinite(n)) return value;
  return new Intl.NumberFormat(locale, { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(n);
}

export function CurrencyInput({
  value,
  defaultValue = null,
  onValueChange,
  currency,
  locale = DEFAULT_LOCALE,
  symbol = 'symbol',
  allowNegative = false,
  onChange,
  onFocus,
  onBlur,
  leading,
  placeholder,
  ...rest
}: CurrencyInputProps) {
  const digits = currencyFractionDigits(currency);
  const isControlled = value !== undefined;
  const [focused, setFocused] = useState(false);
  const [draft, setDraft] = useState<string>(() => normalizeDecimal(isControlled ? value : defaultValue, digits) ?? '');
  const [prevValue, setPrevValue] = useState(value);

  // External controlled change (form reset / async load) → resync draft.
  if (isControlled && value !== prevValue) {
    setPrevValue(value);
    if (normalizeDecimal(draft, digits) !== normalizeDecimal(value, digits)) setDraft(value ?? '');
  }

  const committed = isControlled ? (value ?? null) : normalizeDecimal(draft, digits);
  const display = focused ? draft : formatDisplay(committed, digits, locale);

  const adornment =
    leading ??
    (symbol === 'none' ? undefined : (
      <span className={styles.symbol}>{symbol === 'code' ? currency.toUpperCase() : currencySymbol(currency, locale)}</span>
    ));

  return (
    <Input
      type="text"
      inputMode="decimal"
      autoComplete="off"
      value={display}
      placeholder={placeholder ?? (digits > 0 ? `0.${'0'.repeat(digits)}` : '0')}
      leading={adornment}
      inputClassName={styles.input}
      onFocus={(event: FocusEvent<HTMLInputElement>) => {
        onFocus?.(event);
        setDraft(committed ?? '');
        setFocused(true);
      }}
      onBlur={(event: FocusEvent<HTMLInputElement>) => {
        onBlur?.(event);
        setFocused(false);
        const normalized = normalizeDecimal(draft, digits);
        setDraft(normalized ?? '');
        if (normalized !== committed) onValueChange?.(normalized);
      }}
      onChange={(event) => {
        onChange?.(event);
        const next = sanitize(event.target.value, digits, allowNegative);
        setDraft(next);
        onValueChange?.(normalizeDecimal(next, digits));
      }}
      {...rest}
    />
  );
}
