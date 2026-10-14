import { describe, expect, it } from 'vitest';
import { findNavTrail } from '@/lib/navigation';

const labels = (path: string) => findNavTrail(path).map((i) => i.label);

describe('findNavTrail', () => {
  it('finds a top-level exact item', () => {
    expect(labels('/dashboard')).toEqual(['Dashboard']);
  });

  it('returns parent + child for nested routes', () => {
    expect(labels('/hr/leave')).toEqual(['HR', 'Leave']);
    expect(labels('/finance/journals/new')).toEqual(['Accounting', 'Journal entries']);
  });

  it('prefers the specific child over a parent sharing the href', () => {
    expect(labels('/finance/invoices/12')).toEqual(['Receivables', 'Customer invoices']);
    expect(labels('/company')).toEqual(['Company', 'Profile & settings']);
    expect(labels('/company/branches')).toEqual(['Company', 'Branches']);
  });

  it('does not treat an exact item as a prefix', () => {
    expect(labels('/dashboard/extra')).toEqual([]);
  });

  it('returns an empty trail for unknown routes', () => {
    expect(labels('/nowhere')).toEqual([]);
  });
});
