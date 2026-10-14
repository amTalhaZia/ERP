/**
 * Client-side mirror of the backend DocumentTotalsCalculator, for live preview
 * only (the server recomputes and stores the authoritative totals):
 *
 *   gross          = round2(quantity × unit_price)
 *   line_subtotal  = round2(gross × (1 − discount% / 100))
 *   discount       = gross − line_subtotal
 *   tax_amount     = round2(line_subtotal × rate% / 100)
 *   line_total     = line_subtotal + tax_amount
 *
 * Inputs are 4dp decimal strings; all arithmetic is on BigInt (half-up).
 */
import { fromScaled, toScaled } from '@/features/inventory/decimal';

/** Half-up integer division for non-negative or negative numerators. */
function divRound(n: bigint, d: bigint): bigint {
  const neg = n < 0n;
  const a = neg ? -n : n;
  const q = (a * 2n + d) / (2n * d);
  return neg ? -q : q;
}

/** cents (2dp integer) → 4dp decimal string. */
export const centsToDecimal = (c: bigint) => fromScaled(c * 100n);
/** 4dp decimal string → cents, rounded half-up. */
export const decimalToCents = (v: string | null | undefined) => divRound(toScaled(v), 100n);

export interface LineAmounts {
  gross: bigint;
  subtotal: bigint;
  discount: bigint;
  tax: bigint;
  total: bigint;
}

/** All results in cents. */
export function lineAmounts(quantity: string, unitPrice: string, discountPercent: string, taxRate: string): LineAmounts {
  const q = toScaled(quantity); // ×1e4
  const p = toScaled(unitPrice); // ×1e4
  const gross = divRound(q * p, 1_000_000n); // ×1e8 → ×1e2
  const d = toScaled(discountPercent); // percent ×1e4 → fraction ×1e6
  const subtotal = divRound(gross * (1_000_000n - d), 1_000_000n);
  const r = toScaled(taxRate); // percent ×1e4 → fraction ×1e6
  const tax = divRound(subtotal * r, 1_000_000n);
  return { gross, subtotal, discount: gross - subtotal, tax, total: subtotal + tax };
}

/** Expense claim line: net amount (no qty/discount) + tax. Results in cents. */
export function amountWithTax(amount: string, taxRate: string) {
  const net = decimalToCents(amount);
  const tax = divRound(net * toScaled(taxRate), 1_000_000n);
  return { net, tax, total: net + tax };
}

export function sumTotals(lines: LineAmounts[]) {
  return lines.reduce(
    (acc, l) => ({ subtotal: acc.subtotal + l.subtotal, discount: acc.discount + l.discount, tax: acc.tax + l.tax, total: acc.total + l.total }),
    { subtotal: 0n, discount: 0n, tax: 0n, total: 0n },
  );
}

/** Sanitise typed money to digits + one point, at most `digits` fraction digits. */
export function sanitizeDecimal(text: string, digits = 4): string {
  const out = text.replace(/[^\d.]/g, '');
  const [head, ...tail] = out.split('.');
  return tail.length > 0 ? `${head}.${tail.join('').slice(0, digits)}` : out;
}

/** Trim a 4dp string for display in inputs: "10.5000" → "10.5", "3.0000" → "3". */
export function trimDecimal(v: string | null | undefined): string {
  if (!v) return '';
  if (!v.includes('.')) return v;
  return v.replace(/0+$/, '').replace(/\.$/, '');
}
