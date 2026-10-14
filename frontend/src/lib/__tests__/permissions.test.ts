import { describe, expect, it } from 'vitest';
import { hasPermission } from '@/lib/auth/permissions';

describe('hasPermission', () => {
  const granted = ['invoices.view', 'invoices.create', 'leave.request'];

  it('allows when nothing is required', () => {
    expect(hasPermission(granted, undefined)).toBe(true);
    expect(hasPermission([], [])).toBe(true);
  });

  it('checks a single permission', () => {
    expect(hasPermission(granted, 'invoices.view')).toBe(true);
    expect(hasPermission(granted, 'invoices.approve')).toBe(false);
  });

  it("mode 'all' requires every permission", () => {
    expect(hasPermission(granted, ['invoices.view', 'invoices.create'])).toBe(true);
    expect(hasPermission(granted, ['invoices.view', 'invoices.approve'], 'all')).toBe(false);
  });

  it("mode 'any' requires at least one", () => {
    expect(hasPermission(granted, ['invoices.approve', 'leave.request'], 'any')).toBe(true);
    expect(hasPermission(granted, ['payroll.view', 'journals.view'], 'any')).toBe(false);
  });
});
