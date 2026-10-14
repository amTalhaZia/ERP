import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Pagination, pageRange } from '@/components/ui/Pagination';

describe('Pagination', () => {
  it('disables previous/first on page 1 and shows the summary', () => {
    render(<Pagination page={1} lastPage={5} total={120} perPage={25} onPageChange={() => {}} />);
    expect(screen.getByRole('button', { name: 'Previous page' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'First page' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Next page' })).toBeEnabled();
    expect(screen.getByRole('button', { name: 'Page 1' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText(/Showing/)).toHaveTextContent('Showing 1–25 of 120 results');
  });

  it('calls onPageChange for next and numbered pages', async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();
    render(<Pagination page={2} lastPage={5} total={120} perPage={25} onPageChange={onPageChange} />);
    await user.click(screen.getByRole('button', { name: 'Next page' }));
    expect(onPageChange).toHaveBeenLastCalledWith(3);
    await user.click(screen.getByRole('button', { name: 'Page 5' }));
    expect(onPageChange).toHaveBeenLastCalledWith(5);
    await user.click(screen.getByRole('button', { name: 'Page 2' }));
    expect(onPageChange).toHaveBeenCalledTimes(2); // clicking the current page is a no-op
  });

  it('disables next on the last page', () => {
    render(<Pagination page={5} lastPage={5} total={120} perPage={25} onPageChange={() => {}} />);
    expect(screen.getByRole('button', { name: 'Next page' })).toBeDisabled();
    expect(screen.getByText(/Showing/)).toHaveTextContent('Showing 101–120 of 120 results');
  });

  it('pageRange collapses long ranges with ellipses', () => {
    expect(pageRange(1, 5)).toEqual([1, 2, 3, 4, 5]);
    expect(pageRange(10, 20)).toEqual([1, 'ellipsis', 9, 10, 11, 'ellipsis', 20]);
    expect(pageRange(1, 20)).toEqual([1, 2, 3, 4, 5, 'ellipsis', 20]);
  });
});
