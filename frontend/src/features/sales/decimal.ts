/**
 * Exact 4dp decimal math on scaled BigInts, mirroring the backend's bcmath
 * (`App\Support\Money\Money`, truncating at 4 decimals). Used for the live
 * line-total preview only — the server recomputes and is authoritative.
 */
const SCALE = 10000n;

export function toScaled(value: string | null | undefined): bigint {
  const s = (value ?? '').trim();
  if (s === '' || s === '-' || s === '.') return 0n;
  const neg = s.startsWith('-');
  const [i = '0', f = ''] = s.replace(/^[-+]/, '').split('.');
  const n = BigInt(i.replace(/\D/g, '') || '0') * SCALE + BigInt((f.replace(/\D/g, '') + '0000').slice(0, 4) || '0');
  return neg ? -n : n;
}

export function fromScaled(n: bigint): string {
  const neg = n < 0n;
  const a = neg ? -n : n;
  return `${neg ? '-' : ''}${a / SCALE}.${(a % SCALE).toString().padStart(4, '0')}`;
}

/** Truncating division toward zero (bcmath semantics). */
const div = (a: bigint, b: bigint) => a / b;

/** Money::mul(a, b) at scale 4. */
const mul = (a: bigint, b: bigint) => div(a * b, SCALE);

/** Money::percent(amount, rate) = bcdiv(bcmul(amount, rate, 6), 100, 4). */
const percent = (amount: bigint, rate: bigint) => div(div(amount * rate, 100n), 10000n);

export interface LineAmounts {
  gross: bigint;
  discount: bigint;
  subtotal: bigint;
  tax: bigint;
  total: bigint;
}

/** line_subtotal = qty × price − gross × disc%, tax = subtotal × rate% (DocumentTotals::line). */
export function lineAmounts(quantity: string, unitPrice: string, discountPercent: string | null, taxRate: string | null): LineAmounts {
  const gross = mul(toScaled(quantity), toScaled(unitPrice));
  const discount = percent(gross, toScaled(discountPercent));
  const subtotal = gross - discount;
  const tax = percent(subtotal, toScaled(taxRate));
  return { gross, discount, subtotal, tax, total: subtotal + tax };
}

export function documentTotals(lines: { quantity: string; unit_price: string; discount_percent: string | null; tax_rate: string | null }[]) {
  return lines.reduce(
    (acc, l) => {
      const a = lineAmounts(l.quantity, l.unit_price, l.discount_percent, l.tax_rate);
      return { gross: acc.gross + a.gross, discount: acc.discount + a.discount, subtotal: acc.subtotal + a.subtotal, tax: acc.tax + a.tax, total: acc.total + a.total };
    },
    { gross: 0n, discount: 0n, subtotal: 0n, tax: 0n, total: 0n },
  );
}

export const subDecimals = (a: string | null | undefined, b: string | null | undefined) => fromScaled(toScaled(a) - toScaled(b));
export const sumDecimals = (values: (string | null | undefined)[]) => fromScaled(values.reduce((acc, v) => acc + toScaled(v), 0n));
export const compareDecimals = (a: string | null | undefined, b: string | null | undefined) => {
  const d = toScaled(a) - toScaled(b);
  return d === 0n ? 0 : d > 0n ? 1 : -1;
};
export const isPositive = (v: string | null | undefined) => toScaled(v) > 0n;

/** Keeps digits and one decimal point, at most `digits` fraction digits (typing-time sanitiser). */
export function sanitizeDecimal(text: string, digits = 4): string {
  const out = text.replace(/[^\d.]/g, '');
  const [head, ...tail] = out.split('.');
  return tail.length > 0 ? `${head}.${tail.join('').slice(0, digits)}` : out;
}

/** Display a quantity: trims trailing zeros, groups thousands ("1,250.5"). */
export function formatQty(value: string | null | undefined, empty = '—'): string {
  if (value === null || value === undefined || value === '') return empty;
  const n = toScaled(value);
  const neg = n < 0n;
  const a = neg ? -n : n;
  const int = (a / SCALE).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const frac = (a % SCALE).toString().padStart(4, '0').replace(/0+$/, '');
  return `${neg ? '−' : ''}${int}${frac ? `.${frac}` : ''}`;
}

/** "12.5000" → "12.5" for compact percent inputs. */
export function trimDecimal(value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '';
  return value.includes('.') ? value.replace(/0+$/, '').replace(/\.$/, '') : value;
}
