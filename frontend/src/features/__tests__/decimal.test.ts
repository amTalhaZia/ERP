import { describe, expect, it } from 'vitest';
import * as inv from '@/features/inventory/decimal';
import * as money from '@/features/finance/receivables/money';
import * as proc from '@/features/procurement/decimal';
import * as sales from '@/features/sales/decimal';

describe('inventory/decimal', () => {
  it('round-trips 4dp strings through scaled BigInts', () => {
    expect(inv.toScaled('12.3456')).toBe(123456n);
    expect(inv.toScaled('-0.5')).toBe(-5000n);
    expect(inv.toScaled('1.23456789')).toBe(12345n); // truncated, not rounded
    expect(inv.toScaled('')).toBe(0n);
    expect(inv.toScaled(null)).toBe(0n);
    expect(inv.fromScaled(-5000n)).toBe('-0.5000');
  });

  it('sums without floating-point drift', () => {
    expect(inv.sumDecimals(['0.1', '0.2'])).toBe('0.3000');
    expect(inv.sumDecimals(['10.0000', '-2.5000', null])).toBe('7.5000');
    expect(inv.subDecimals('5', '7.25')).toBe('-2.2500');
  });

  it('compares and checks sign', () => {
    expect(inv.compareDecimals('1.0001', '1')).toBe(1);
    expect(inv.compareDecimals('2', '2.0000')).toBe(0);
    expect(inv.compareDecimals('-1', '0')).toBe(-1);
    expect(inv.isPositive('0.0001')).toBe(true);
    expect(inv.isPositive('0')).toBe(false);
    expect(inv.isNegative('-3')).toBe(true);
  });

  it('sanitises typed quantities and formats for display', () => {
    expect(inv.sanitizeQty('1a2.3.4567')).toBe('12.3456');
    expect(inv.sanitizeQty('abc')).toBe('');
    expect(inv.formatQty('1250.5000')).toBe('1,250.5');
    expect(inv.formatQty('-3.0000')).toBe('−3');
    expect(inv.formatQty('4', { signed: true })).toBe('+4');
    expect(inv.formatQty(null)).toBe('—');
  });
});

describe('finance/receivables/money', () => {
  it('computes invoice totals with discount and tax (half-up)', () => {
    // 2 × 100.00 @ 10% discount, 15% tax
    const line = money.lineAmounts('2', '100.00', '10', '15');
    expect(line.gross).toBe(20000n);
    expect(money.centsToDecimal(line.subtotal)).toBe('180.0000');
    expect(money.centsToDecimal(line.discount)).toBe('20.0000');
    expect(money.centsToDecimal(line.tax)).toBe('27.0000');
    expect(money.centsToDecimal(line.total)).toBe('207.0000');
  });

  it('rounds half-up to cents', () => {
    // 1 × 0.125 → 0.13 gross; 3 × 3.3333 = 9.9999 → 10.00
    expect(money.lineAmounts('1', '0.125', '0', '0').gross).toBe(13n);
    expect(money.lineAmounts('3', '3.3333', '0', '0').gross).toBe(1000n);
    // tax 10.01 × 5% = 0.5005 → 0.50
    expect(money.lineAmounts('1', '10.01', '0', '5').tax).toBe(50n);
    expect(money.decimalToCents('-1.005')).toBe(-101n);
  });

  it('sums line totals', () => {
    const a = money.lineAmounts('2', '100.00', '10', '15');
    const b = money.lineAmounts('1', '50', '0', '0');
    const t = money.sumTotals([a, b]);
    expect(money.centsToDecimal(t.subtotal)).toBe('230.0000');
    expect(money.centsToDecimal(t.tax)).toBe('27.0000');
    expect(money.centsToDecimal(t.total)).toBe('257.0000');
  });

  it('computes expense amount + tax and trims for inputs', () => {
    const r = money.amountWithTax('100', '17');
    expect(r).toEqual({ net: 10000n, tax: 1700n, total: 11700n });
    expect(money.trimDecimal('10.5000')).toBe('10.5');
    expect(money.trimDecimal('3.0000')).toBe('3');
    expect(money.trimDecimal(null)).toBe('');
    expect(money.sanitizeDecimal('12.34567', 2)).toBe('12.34');
  });
});

describe('procurement/decimal', () => {
  it('parses with truncation and rejects non-numeric input', () => {
    expect(proc.toUnits('12.345678')).toBe(123456n);
    expect(proc.toUnits('1,000.5')).toBe(10005000n);
    expect(proc.toUnits('12abc')).toBe(0n);
    expect(proc.toUnits(7)).toBe(70000n);
    expect(proc.fromUnits(123456n)).toBe('12.3456');
  });

  it('computes document totals with truncating bcmath semantics and shipping', () => {
    const doc = proc.documentAmounts(
      [
        { quantity: '2', unit_price: '100', discount_percent: '10', tax_rate: '15' },
        { quantity: '3', unit_price: '3.3333' },
      ],
      '25',
    );
    expect(proc.fromUnits(doc.lines[0].subtotal)).toBe('180.0000');
    expect(proc.fromUnits(doc.lines[0].tax)).toBe('27.0000');
    expect(proc.fromUnits(doc.lines[1].gross)).toBe('9.9999');
    expect(proc.fromUnits(doc.subtotal)).toBe('189.9999');
    expect(proc.fromUnits(doc.discount)).toBe('20.0000');
    expect(proc.fromUnits(doc.total)).toBe('241.9999');
  });

  it('truncates percentages toward zero', () => {
    // 0.0003 × 50% = 0.00015 → 0.0001
    expect(proc.percent(3n, 500000n)).toBe(1n);
  });

  it('formats quantities', () => {
    expect(proc.formatQty('10.5000')).toBe('10.5');
    expect(proc.formatQty('100.0000')).toBe('100');
    expect(proc.formatQty('0')).toBe('0');
    expect(proc.formatQty('')).toBe('—');
    expect(proc.isPositive('0.0001')).toBe(true);
  });
});

describe('sales/decimal', () => {
  it('computes line amounts (truncating)', () => {
    const l = sales.lineAmounts('2', '100.00', '10', '15');
    expect(sales.fromScaled(l.gross)).toBe('200.0000');
    expect(sales.fromScaled(l.discount)).toBe('20.0000');
    expect(sales.fromScaled(l.subtotal)).toBe('180.0000');
    expect(sales.fromScaled(l.tax)).toBe('27.0000');
    expect(sales.fromScaled(l.total)).toBe('207.0000');
    // null discount / tax treated as zero
    expect(sales.fromScaled(sales.lineAmounts('1.5', '2', null, null).total)).toBe('3.0000');
  });

  it('aggregates document totals', () => {
    const t = sales.documentTotals([
      { quantity: '2', unit_price: '100.00', discount_percent: '10', tax_rate: '15' },
      { quantity: '1', unit_price: '0.3333', discount_percent: null, tax_rate: '10' },
    ]);
    expect(sales.fromScaled(t.subtotal)).toBe('180.3333');
    expect(sales.fromScaled(t.tax)).toBe('27.0333');
    expect(sales.fromScaled(t.total)).toBe('207.3666');
  });

  it('helpers: sum, compare, format, trim', () => {
    expect(sales.sumDecimals(['0.1', '0.2', '0.3'])).toBe('0.6000');
    expect(sales.subDecimals('1', '0.0001')).toBe('0.9999');
    expect(sales.compareDecimals('1', '1.0001')).toBe(-1);
    expect(sales.formatQty('1234567.8900')).toBe('1,234,567.89');
    expect(sales.formatQty(undefined, 'none')).toBe('none');
    expect(sales.trimDecimal('12.5000')).toBe('12.5');
    expect(sales.sanitizeDecimal('-1.23456')).toBe('1.2345');
  });
});
