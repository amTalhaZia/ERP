import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Sidebar } from '@/components/layout/Sidebar';
import { makeSession, renderWithProviders } from '@/test/render';

const nav = vi.hoisted(() => ({ pathname: '/dashboard' }));

vi.mock('next/navigation', () => ({
  usePathname: () => nav.pathname,
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), back: vi.fn(), prefetch: vi.fn(), refresh: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

/** The desktop sidebar (the mobile drawer is closed by default). */
const mainNav = () => screen.getByRole('navigation', { name: 'Main navigation' });

describe('Sidebar', () => {
  beforeEach(() => {
    nav.pathname = '/dashboard';
  });

  it('shows only HR > Leave for a user with just leave.request', () => {
    nav.pathname = '/hr/leave';
    renderWithProviders(<Sidebar />, { session: makeSession({ permissions: ['leave.request'] }) });
    const n = within(mainNav());

    expect(n.getByRole('button', { name: 'HR' })).toHaveAttribute('aria-expanded', 'true');
    const leave = n.getByRole('link', { name: 'Leave' });
    expect(leave).toHaveAttribute('href', '/hr/leave');
    expect(leave).toHaveAttribute('aria-current', 'page');

    expect(n.queryByRole('link', { name: 'Employees' })).not.toBeInTheDocument();
    expect(n.queryByText('Payroll')).not.toBeInTheDocument();
    expect(n.queryByText('Finance')).not.toBeInTheDocument();
    expect(n.queryByText('Accounting')).not.toBeInTheDocument();
    expect(n.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
    expect(n.queryByText('Platform')).not.toBeInTheDocument();
  });

  it('filters children within a permitted group and expands on click', async () => {
    const user = userEvent.setup();
    renderWithProviders(<Sidebar />, { session: makeSession({ permissions: ['dashboard.view', 'invoices.view'] }) });
    const n = within(mainNav());

    expect(n.getByRole('link', { name: 'Dashboard' })).toHaveAttribute('aria-current', 'page');
    const receivables = n.getByRole('button', { name: 'Receivables' });
    expect(receivables).toHaveAttribute('aria-expanded', 'false');
    await user.click(receivables);
    expect(n.getByRole('link', { name: 'Customer invoices' })).toBeInTheDocument();
    expect(n.queryByRole('link', { name: 'Receipts' })).not.toBeInTheDocument();
    expect(n.queryByRole('button', { name: 'Payables' })).not.toBeInTheDocument();
  });

  it('shows the platform section only to platform admins', () => {
    renderWithProviders(<Sidebar />, {
      session: makeSession({ permissions: [], user: { is_platform_admin: true, platform_role: 'platform.owner' } }),
    });
    const n = within(mainNav());
    expect(n.getByText('Platform')).toBeInTheDocument();
    expect(n.getByRole('link', { name: 'Companies' })).toHaveAttribute('href', '/platform/companies');
    expect(n.getByRole('link', { name: 'System' })).toBeInTheDocument();
    // Platform role never implies company navigation.
    expect(n.queryByRole('link', { name: 'Dashboard' })).not.toBeInTheDocument();
  });

  it('hides the platform section from company users even with broad permissions', () => {
    renderWithProviders(<Sidebar />, {
      session: makeSession({ permissions: ['dashboard.view', 'payroll.view', 'accounts.view', 'platform.companies.manage'] }),
    });
    const n = within(mainNav());
    expect(n.getByRole('button', { name: 'Payroll' })).toBeInTheDocument();
    expect(n.getByRole('button', { name: 'Accounting' })).toBeInTheDocument();
    expect(n.queryByText('Platform')).not.toBeInTheDocument();
    expect(n.queryByRole('link', { name: 'Companies' })).not.toBeInTheDocument();
  });
});
