/**
 * Exact 4dp decimal helpers (quantities and costs are DECIMAL(18,4) strings).
 * Arithmetic is done on scaled BigInts — never floats.
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

export const sumDecimals = (values: (string | null | undefined)[]) => fromScaled(values.reduce((acc, v) => acc + toScaled(v), 0n));
export const subDecimals = (a: string | null | undefined, b: string | null | undefined) => fromScaled(toScaled(a) - toScaled(b));
export const isPositive = (v: string | null | undefined) => toScaled(v) > 0n;
export const isNegative = (v: string | null | undefined) => toScaled(v) < 0n;
export const compareDecimals = (a: string | null | undefined, b: string | null | undefined) => {
  const d = toScaled(a) - toScaled(b);
  return d === 0n ? 0 : d > 0n ? 1 : -1;
};

/** Keeps digits and one decimal point, at most 4 fraction digits (typing-time sanitiser). */
export function sanitizeQty(text: string, digits = 4): string {
  const out = text.replace(/[^\d.]/g, '');
  const [head, ...tail] = out.split('.');
  return tail.length > 0 ? `${head}.${tail.join('').slice(0, digits)}` : out;
}

/** Display a quantity: trims trailing zeros, groups thousands ("1,250.5"). */
export function formatQty(value: string | null | undefined, opts: { signed?: boolean; empty?: string } = {}): string {
  if (value === null || value === undefined || value === '') return opts.empty ?? '—';
  const n = toScaled(value);
  const neg = n < 0n;
  const a = neg ? -n : n;
  const int = (a / SCALE).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  const frac = (a % SCALE).toString().padStart(4, '0').replace(/0+$/, '');
  const sign = neg ? '−' : opts.signed && n > 0n ? '+' : '';
  return `${sign}${int}${frac ? `.${frac}` : ''}`;
}
