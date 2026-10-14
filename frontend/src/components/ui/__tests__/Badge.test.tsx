import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { StatusBadge, resolveStatus } from '@/components/ui/Badge';

describe('StatusBadge', () => {
  it('maps known statuses to variant + label', () => {
    expect(resolveStatus('posted')).toEqual({ variant: 'info', label: 'Posted' });
    expect(resolveStatus('overdue')).toEqual({ variant: 'danger', label: 'Overdue' });
    expect(resolveStatus('Partially Paid')).toEqual({ variant: 'warning', label: 'Partially paid' });
    expect(resolveStatus('in-progress')).toEqual({ variant: 'info', label: 'In progress' });
  });

  it('falls back to neutral + humanised text for unknown statuses', () => {
    expect(resolveStatus('awaiting_customer')).toEqual({ variant: 'neutral', label: 'Awaiting customer' });
  });

  it('renders the mapped label and variant class; overrides win', () => {
    const { rerender } = render(<StatusBadge status="approved" />);
    expect(screen.getByText('Approved')).toHaveClass('badge', 'success');
    rerender(<StatusBadge status="approved" label="OK" variant="primary" />);
    expect(screen.getByText('OK')).toHaveClass('primary');
  });
});
