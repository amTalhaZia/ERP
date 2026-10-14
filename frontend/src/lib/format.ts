/**
 * Display formatting helpers.
 *
 * MONEY: the API always transports amounts as decimal strings. These helpers
 * only convert to a JS number at the very last step, for *display* via Intl.
 * Never store, compare or do arithmetic on the numeric result — keep the
 * string and let the backend (or a decimal library) do the math.
 */
import { format as dfFormat, formatDistanceToNow, isValid, parseISO } from 'date-fns';

export const DEFAULT_LOCALE = 'en-US';
export const EM_DASH = '—';

type DateInput = string | number | Date | null | undefined;

function toDate(value: DateInput): Date | null {
  if (value === null || value === undefined || value === '') return null;
  const d = typeof value === 'string' ? parseISO(value) : new Date(value);
  return isValid(d) ? d : null;
}

function toDisplayNumber(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined || value === '') return null;
  const n = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(n) ? n : null;
}

export interface FormatMoneyOptions {
  /** Defaults to the currency's standard minor-unit digits. */
  minimumFractionDigits?: number;
  maximumFractionDigits?: number;
  /** `'always'` renders a leading + for positive amounts (useful in ledgers). */
  signDisplay?: 'auto' | 'always' | 'never' | 'exceptZero';
  /** `'code'` renders "USD 1,234.00", `'symbol'` renders "$1,234.00". */
  currencyDisplay?: 'symbol' | 'narrowSymbol' | 'code' | 'name';
  /** Text shown for null / empty values. Defaults to an em dash. */
  empty?: string;
}

/** Formats a decimal-string (or number) amount as currency for display only. */
export function formatMoney(
  amount: string | number | null | undefined,
  currency: string,
  locale: string = DEFAULT_LOCALE,
  options: FormatMoneyOptions = {},
): string {
  const { empty = EM_DASH, ...intl } = options;
  const n = toDisplayNumber(amount);
  if (n === null) return empty;
  try {
    return new Intl.NumberFormat(locale, { style: 'currency', currency, ...intl }).format(n);
  } catch {
    // Unknown / malformed currency code — degrade gracefully instead of throwing in render.
    return `${currency} ${n.toFixed(2)}`;
  }
}

/** Returns the currency symbol for a code in the given locale (e.g. "$", "€", "PKR"). */
export function currencySymbol(currency: string, locale: string = DEFAULT_LOCALE): string {
  try {
    const part = new Intl.NumberFormat(locale, { style: 'currency', currency, currencyDisplay: 'narrowSymbol' })
      .formatToParts(0)
      .find((p) => p.type === 'currency');
    return part?.value ?? currency;
  } catch {
    return currency;
  }
}

/** Number of minor-unit digits a currency uses (2 for USD, 0 for JPY, 3 for KWD). */
export function currencyFractionDigits(currency: string): number {
  try {
    return new Intl.NumberFormat(DEFAULT_LOCALE, { style: 'currency', currency }).resolvedOptions().maximumFractionDigits ?? 2;
  } catch {
    return 2;
  }
}

export interface FormatNumberOptions extends Intl.NumberFormatOptions {
  empty?: string;
}

export function formatNumber(
  value: string | number | null | undefined,
  options: FormatNumberOptions = {},
  locale: string = DEFAULT_LOCALE,
): string {
  const { empty = EM_DASH, ...intl } = options;
  const n = toDisplayNumber(value);
  if (n === null) return empty;
  return new Intl.NumberFormat(locale, intl).format(n);
}

/** Compact notation: 1.2K, 3.4M. */
export function formatCompact(value: string | number | null | undefined, locale: string = DEFAULT_LOCALE): string {
  return formatNumber(value, { notation: 'compact', maximumFractionDigits: 1 }, locale);
}

/**
 * Formats a ratio or percentage for display.
 * @param value The percentage value (e.g. 12.5 means 12.5%). Pass `{ ratio: true }` if the value is 0–1.
 */
export function formatPercent(
  value: string | number | null | undefined,
  options: { fractionDigits?: number; ratio?: boolean; signDisplay?: 'auto' | 'always' | 'exceptZero'; empty?: string } = {},
  locale: string = DEFAULT_LOCALE,
): string {
  const { fractionDigits = 1, ratio = false, signDisplay = 'auto', empty = EM_DASH } = options;
  const n = toDisplayNumber(value);
  if (n === null) return empty;
  return new Intl.NumberFormat(locale, {
    style: 'percent',
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits,
    signDisplay,
  }).format(ratio ? n : n / 100);
}

export const DATE_PATTERN = 'dd MMM yyyy';
export const DATETIME_PATTERN = 'dd MMM yyyy, HH:mm';

/** Formats an ISO date (yyyy-mm-dd) or datetime string. */
export function formatDate(value: DateInput, pattern: string = DATE_PATTERN, empty: string = EM_DASH): string {
  const d = toDate(value);
  return d ? dfFormat(d, pattern) : empty;
}

export function formatDateTime(value: DateInput, pattern: string = DATETIME_PATTERN, empty: string = EM_DASH): string {
  return formatDate(value, pattern, empty);
}

/** "3 hours ago", "in 2 days". */
export function formatRelative(value: DateInput, empty: string = EM_DASH): string {
  const d = toDate(value);
  return d ? formatDistanceToNow(d, { addSuffix: true }) : empty;
}

/** Keeps the start and end of a long string: "INV-2024-00…-00912". */
export function truncateMiddle(value: string, maxLength = 24, separator = '…'): string {
  if (!value || value.length <= maxLength) return value;
  const keep = maxLength - separator.length;
  const head = Math.ceil(keep / 2);
  const tail = Math.floor(keep / 2);
  return `${value.slice(0, head)}${separator}${value.slice(value.length - tail)}`;
}

/** Initials for avatars: "Adnan Bilal" → "AB", "adnan" → "A". */
export function initials(name: string | null | undefined, max = 2): string {
  if (!name) return '';
  return name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, max)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('');
}

/** Humanises snake_case / kebab-case identifiers: "in_progress" → "In progress". */
export function humanize(value: string): string {
  const spaced = value.replace(/[_-]+/g, ' ').trim();
  return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
