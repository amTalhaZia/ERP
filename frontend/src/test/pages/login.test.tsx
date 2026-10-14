import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import LoginPage from '@/app/(auth)/login/page';
import { authApi } from '@/features/auth/api';
import { makeSession, renderWithProviders } from '@/test/render';

const mocks = vi.hoisted(() => ({
  replace: vi.fn(),
  applySession: vi.fn(),
  search: '',
}));

vi.mock('next/navigation', () => ({
  usePathname: () => '/login',
  useRouter: () => ({ push: vi.fn(), replace: mocks.replace, back: vi.fn(), prefetch: vi.fn(), refresh: vi.fn() }),
  useSearchParams: () => new URLSearchParams(mocks.search),
}));

vi.mock('@/features/auth/api', () => ({
  authApi: { login: vi.fn() },
}));

vi.mock('@/lib/auth/SessionProvider', () => ({
  useSession: () => ({ applySession: mocks.applySession, refresh: vi.fn(), logout: vi.fn(), switchCompany: vi.fn() }),
}));

const login = vi.mocked(authApi.login);

async function fillAndSubmit(email: string, password: string) {
  const user = userEvent.setup();
  if (email) await user.type(screen.getByLabelText('Email'), email);
  if (password) await user.type(screen.getByLabelText('Password'), password);
  await user.click(screen.getByRole('button', { name: 'Sign in' }));
  return user;
}

describe('Login page', () => {
  beforeEach(() => {
    login.mockReset();
    mocks.replace.mockReset();
    mocks.applySession.mockReset();
    mocks.search = '';
  });

  it('validates empty fields client-side without calling the API', async () => {
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('', '');
    expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
    expect(screen.getByText('Enter your password')).toBeInTheDocument();
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-describedby', 'email-error');
    expect(login).not.toHaveBeenCalled();
  });

  it('rejects an invalid email', async () => {
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('not-an-email', 'secret');
    expect(await screen.findByText('Enter a valid email address')).toBeInTheDocument();
    expect(screen.queryByText('Enter your password')).not.toBeInTheDocument();
    expect(login).not.toHaveBeenCalled();
  });

  it('submits credentials and shows the server 422 message under email', async () => {
    login.mockRejectedValue({
      status: 422,
      code: 'validation.failed',
      message: 'These credentials do not match our records.',
      errors: { email: ['These credentials do not match our records.'] },
    });
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('jane@acme.test', 'wrong-password');

    await waitFor(() => expect(login).toHaveBeenCalledWith({ email: 'jane@acme.test', password: 'wrong-password', remember: true }));
    const msg = await screen.findByText('These credentials do not match our records.');
    expect(msg).toHaveAttribute('id', 'email-error');
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-invalid', 'true');
    expect(mocks.replace).not.toHaveBeenCalled();
  });

  it('shows a form-level alert for non-validation errors', async () => {
    login.mockRejectedValue({ status: 429, code: 'throttled', message: 'Too many attempts. Please wait a moment and try again.' });
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('jane@acme.test', 'pw');
    expect(await screen.findByRole('alert')).toHaveTextContent('Too many attempts');
  });

  it('redirects to /two-factor when the server requires 2FA', async () => {
    login.mockResolvedValue({ two_factor: true });
    mocks.search = 'next=/finance/invoices';
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('jane@acme.test', 'pw');
    await waitFor(() => expect(mocks.replace).toHaveBeenCalledWith('/two-factor?next=%2Ffinance%2Finvoices'));
    expect(mocks.applySession).not.toHaveBeenCalled();
  });

  it('applies the session and goes to the dashboard on success', async () => {
    const session = makeSession({ permissions: ['dashboard.view'] });
    login.mockResolvedValue({ two_factor: false, session });
    renderWithProviders(<LoginPage />);
    await fillAndSubmit('jane@acme.test', 'pw');
    await waitFor(() => expect(mocks.replace).toHaveBeenCalledWith('/dashboard'));
    expect(mocks.applySession).toHaveBeenCalledWith(session);
  });
});
