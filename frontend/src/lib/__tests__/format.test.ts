import { describe, expect, it } from 'vitest';
import { formatDate, formatMoney, formatPercent, humanize, truncateMiddle } from '@/lib/format';

describe('formatMoney', () => {
  it('formats decimal strings without float noise', () => {
    expect(formatMoney('1234.5000', 'USD')).toBe('$1,234.50');
    expect(formatMoney('0.1000', 'USD')).toBe('$0.10');
    expect(formatMoney('-42.0000', 'USD')).toBe('-$42.00');
  });

  it('respects currency minor units', () => {
    expect(formatMoney('1500.0000', 'JPY')).toBe('¥1,500');
  });

  it('returns the empty placeholder for null / empty / garbage', () => {
    expect(formatMoney(null, 'USD')).toBe('—');
    expect(formatMoney('', 'USD', 'en-US', { empty: 'n/a' })).toBe('n/a');
    expect(formatMoney('abc', 'USD')).toBe('—');
  });

  it('degrades gracefully on an invalid currency code', () => {
    expect(formatMoney('10', 'NOT_A_CODE')).toBe('NOT_A_CODE 10.00');
  });
});

describe('formatPercent', () => {
  it('treats the value as a percentage by default', () => {
    expect(formatPercent(12.5)).toBe('12.5%');
    expect(formatPercent('7', { fractionDigits: 0 })).toBe('7%');
  });
  it('supports ratios and sign display', () => {
    expect(formatPercent(0.25, { ratio: true, fractionDigits: 0 })).toBe('25%');
    expect(formatPercent(3, { signDisplay: 'always' })).toBe('+3.0%');
    expect(formatPercent(null)).toBe('—');
  });
});

describe('formatDate', () => {
  it('formats ISO dates with the default pattern', () => {
    expect(formatDate('2026-03-07')).toBe('07 Mar 2026');
    expect(formatDate('2026-03-07', 'yyyy/MM/dd')).toBe('2026/03/07');
  });
  it('returns the placeholder for missing or invalid input', () => {
    expect(formatDate(null)).toBe('—');
    expect(formatDate('not-a-date')).toBe('—');
  });
});

describe('truncateMiddle', () => {
  it('keeps short strings intact', () => {
    expect(truncateMiddle('INV-1', 10)).toBe('INV-1');
  });
  it('keeps head and tail of long strings', () => {
    const out = truncateMiddle('INV-2024-0000000912', 10);
    expect(out).toHaveLength(10);
    expect(out).toBe('INV-2…0912');
  });
});

describe('humanize', () => {
  it('turns identifiers into sentence case', () => {
    expect(humanize('in_progress')).toBe('In progress');
    expect(humanize('partially-paid')).toBe('Partially paid');
    expect(humanize('draft')).toBe('Draft');
  });
});
