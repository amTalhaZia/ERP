/**
 * Exact 4-dp decimal arithmetic with BigInt, mirroring the backend's bcmath
 * `Money` helper (scale 4, truncating) so live previews match the server.
 * The server stays authoritative — these numbers are for display only.
 */
const SCALE = 10000n;

/** "12.345678" → 123456n (truncated to 4 dp, like bcadd($x, '0', 4)). */
export function toUnits(value: string | number | null | undefined): bigint {
  const s = String(value ?? '').trim().replace(/[,\s]/g, '');
  if (!/^-?\d*(\.\d*)?$/.test(s) || s === '' || s === '-' || s === '.') return 0n;
  const neg = s.startsWith('-');
  const [i = '0', f = ''] = s.replace('-', '').split('.');
  const n = BigInt(i || '0') * SCALE + BigInt((f + '0000').slice(0, 4) || '0');
  return neg ? -n : n;
}

/** 123456n → "12.3456" */
export function fromUnits(n: bigint): string {
  const neg = n < 0n;
  const a = neg ? -n : n;
  return `${neg ? '-' : ''}${a / SCALE}.${(a % SCALE).toString().padStart(4, '0')}`;
}

/** bcmul(a, b, 4) — truncates toward zero. */
export const mul = (a: bigint, b: bigint) => (a * b) / SCALE;
/** Money::percent(amount, rate) = amount × rate / 100, truncated to 4 dp. */
export const percent = (amount: bigint, rate: bigint) => (amount * rate) / (SCALE * 100n);

export interface LineAmounts {
  gross: bigint;
  discount: bigint;
  subtotal: bigint;
  tax: bigint;
  total: bigint;
}

/** DocumentTotals::line — gross = qty × price; subtotal = gross − disc%; tax = subtotal × rate%. */
export function lineAmounts(quantity: string, unitPrice: string, discountPercent = '0', taxRate = '0'): LineAmounts {
  const gross = mul(toUnits(quantity), toUnits(unitPrice));
  const discount = percent(gross, toUnits(discountPercent));
  const subtotal = gross - discount;
  const tax = percent(subtotal, toUnits(taxRate));
  return { gross, discount, subtotal, tax, total: subtotal + tax };
}

export interface DocumentAmounts {
  lines: LineAmounts[];
  subtotal: bigint;
  discount: bigint;
  tax: bigint;
  shipping: bigint;
  total: bigint;
}

export function documentAmounts(
  lines: { quantity: string; unit_price: string; discount_percent?: string; tax_rate?: string }[],
  shipping = '0',
): DocumentAmounts {
  const computed = lines.map((l) => lineAmounts(l.quantity, l.unit_price, l.discount_percent, l.tax_rate));
  const subtotal = computed.reduce((a, l) => a + l.subtotal, 0n);
  const discount = computed.reduce((a, l) => a + l.discount, 0n);
  const tax = computed.reduce((a, l) => a + l.tax, 0n);
  const ship = toUnits(shipping);
  return { lines: computed, subtotal, discount, tax, shipping: ship, total: subtotal + tax + ship };
}

/** Keep digits and one decimal point; cap fraction digits. For quantity / percent text inputs. */
export function sanitizeDecimal(text: string, digits = 4): string {
  const out = text.replace(/[^\d.]/g, '');
  const [head, ...tail] = out.split('.');
  return tail.length > 0 ? `${head}.${tail.join('').slice(0, digits)}` : out;
}

/** Trim trailing zeros for display of quantities: "10.5000" → "10.5". */
export function formatQty(value: string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—';
  const s = fromUnits(toUnits(value));
  return s.replace(/\.?0+$/, '') || '0';
}

export const isPositive = (value: string | null | undefined) => toUnits(value) > 0n;
