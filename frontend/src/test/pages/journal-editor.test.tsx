import { Suspense } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import JournalEntryPage from '@/app/(app)/finance/journals/[id]/page';
import { api, get } from '@/lib/api/client';
import { makeSession, renderWithProviders } from '@/test/render';

vi.mock('next/navigation', () => ({
  usePathname: () => '/finance/journals/new',
  useRouter: () => ({ push: vi.fn(), replace: vi.fn(), back: vi.fn(), prefetch: vi.fn(), refresh: vi.fn() }),
  useSearchParams: () => new URLSearchParams(),
}));

vi.mock('@/lib/api/client', () => ({
  API_BASE: 'http://api.test',
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  del: vi.fn(),
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  ensureCsrfCookie: vi.fn(() => Promise.resolve()),
  setApiCompanyId: vi.fn(),
  setUnauthorizedHandler: vi.fn(),
}));

const accountsPage = {
  data: [
    { id: 1, code: '1000', name: 'Cash', is_active: true },
    { id: 2, code: '4000', name: 'Sales', is_active: true },
  ],
  meta: { pagination: { page: 1, per_page: 100, total: 2, last_page: 1 } },
};

async function renderNew(permissions = ['journals.view', 'journals.create']) {
  // Pre-resolved promise tagged the way React's `use()` recognises, so the page renders without suspending.
  const value = { id: 'new' };
  const params = Object.assign(Promise.resolve(value), { status: 'fulfilled', value });
  renderWithProviders(
    <Suspense fallback={<p>loading</p>}>
      <JournalEntryPage params={params} />
    </Suspense>,
    { session: makeSession({ permissions }) },
  );
}

describe('Journal entry editor', () => {
  beforeEach(() => {
    vi.mocked(get).mockResolvedValue(accountsPage);
  });

  it('starts unbalanced with Save disabled', async () => {
    await renderNew();
    expect(await screen.findByText('Enter amounts')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeDisabled();
    expect(get).toHaveBeenCalledWith('/accounts', expect.objectContaining({ per_page: 100 }));
  });

  it('shows "Balanced" and enables Save when debits equal credits', async () => {
    const user = userEvent.setup();
    await renderNew();
    await user.type(await screen.findByLabelText('Line 1 debit'), '100');
    expect(screen.getByText(/Out of balance by/)).toHaveTextContent('Out of balance by $100.00');
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeDisabled();

    await user.type(screen.getByLabelText('Line 2 credit'), '100');
    expect(screen.getByText('Balanced')).toBeInTheDocument();
    expect(screen.queryByText(/Out of balance by/)).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeEnabled();
  });

  it('reports the exact difference when unbalanced', async () => {
    const user = userEvent.setup();
    await renderNew();
    await user.type(await screen.findByLabelText('Line 1 debit'), '100.10');
    await user.type(screen.getByLabelText('Line 2 credit'), '99.95');
    expect(screen.getByText(/Out of balance by/)).toHaveTextContent('Out of balance by $0.15');
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeDisabled();
  });

  it('posts the balanced payload on save', async () => {
    const user = userEvent.setup();
    vi.mocked(api.post).mockResolvedValue({ data: { data: { id: 99 } } });
    await renderNew();
    await screen.findByRole('option', { name: '1000 · Cash' });
    await user.selectOptions(screen.getByLabelText('Line 1 account'), '1');
    await user.selectOptions(screen.getByLabelText('Line 2 account'), '2');
    await user.type(screen.getByLabelText('Line 1 debit'), '250');
    await user.type(screen.getByLabelText('Line 2 credit'), '250');
    await user.click(screen.getByRole('button', { name: 'Save draft' }));

    await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
    const [url, body] = vi.mocked(api.post).mock.calls[0];
    expect(url).toBe('/journal-entries');
    expect(body).toMatchObject({
      lines: [
        { account_id: 1, debit: '250.00', credit: '0' },
        { account_id: 2, debit: '0', credit: '250.00' },
      ],
    });
  });

  it('refuses access without journals.create', async () => {
    await renderNew(['journals.view']);
    expect(await screen.findByText(/permission to create journal entries/)).toBeInTheDocument();
  });
});
