import { describe, expect, it } from 'vitest';
import { auditActionLabel, auditActionPhrase } from '@/lib/auditLabels';

describe('auditActionLabel', () => {
  it.each([
    ['holidays.created', 'Added a holiday'],
    ['holidays.deleted', 'Deleted a holiday'],
    ['performance.reviews.submitted', 'Sent a performance review for approval'],
    ['payroll.run_approved', 'Approved a payroll run'],
    ['sales.orders.created', 'Added a sales order'],
    ['sales_orders.confirmed', 'Confirmed a sales order'],
    ['users.created', 'Added a user'],
    ['units.of.measure.created', 'Added a unit of measure'],
    ['employees.created', 'Added an employee'],
    ['auth.login_failed', 'Failed sign-in attempt'],
    ['deliveries.delivered', 'Marked a delivery as delivered'],
  ])('%s → %s', (code, label) => {
    expect(auditActionLabel(code)).toBe(label);
  });

  it('falls back to readable words for unknown codes', () => {
    expect(auditActionLabel('widgets.frobnicated')).toBe('Widgets frobnicated');
  });

  it('gives a lower-case phrase for use after a name', () => {
    expect(auditActionPhrase('holidays.created')).toBe('added a holiday');
  });
});
