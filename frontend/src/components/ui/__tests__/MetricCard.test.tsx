import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MetricCard } from '@/components/ui/MetricCard';

describe('MetricCard', () => {
  it('unavailable shows "No data" text and never a number', () => {
    const { container } = render(<MetricCard label="Revenue" value={125000} unavailable change={{ value: 12, direction: 'up', positiveIsGood: true }} />);
    expect(screen.getByText(/no data/i)).toBeInTheDocument();
    expect(container.textContent).not.toMatch(/\d/);
  });

  it('supports custom unavailable text', () => {
    render(<MetricCard label="Margin" value="$1,000.00" unavailable unavailableText="No data yet" />);
    expect(screen.getByText('No data yet')).toBeInTheDocument();
    expect(screen.queryByText('$1,000.00')).not.toBeInTheDocument();
  });

  it('renders a pre-formatted value and change when available', () => {
    render(<MetricCard label="Revenue" value="$1,000.00" change={{ value: 12.5, direction: 'down', positiveIsGood: true }} />);
    expect(screen.getByText('$1,000.00')).toBeInTheDocument();
    expect(screen.getByText('12.5%')).toBeInTheDocument();
    expect(screen.getByText('decrease')).toBeInTheDocument();
  });
});
