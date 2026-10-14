import type { ReactElement, ReactNode } from 'react';
import { render, type RenderOptions } from '@testing-library/react';
import { Provider as ReduxProvider } from 'react-redux';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MotionConfig } from 'framer-motion';
import { makeStore, type AppStore } from '@/store';
import { sessionResolved } from '@/store/sessionSlice';
import { ToastProvider } from '@/components/ui/Toast';
import type { CompanyMembership, SessionPayload, SessionUser } from '@/features/auth/types';

export const testCompany: CompanyMembership = {
  id: 1,
  name: 'Acme Ltd',
  slug: 'acme',
  logo_url: null,
  currency: 'USD',
  timezone: 'UTC',
  role: 'member',
  branch_ids: null,
  is_default: true,
};

export function makeSession(overrides: { permissions?: string[]; user?: Partial<SessionUser>; company?: Partial<CompanyMembership> | null } = {}): SessionPayload {
  const company = overrides.company === null ? null : { ...testCompany, ...overrides.company };
  return {
    user: {
      id: 1,
      name: 'Test User',
      email: 'test@example.com',
      avatar_url: null,
      is_platform_admin: false,
      platform_role: null,
      two_factor_enabled: false,
      email_verified_at: '2026-01-01T00:00:00Z',
      last_login_at: null,
      preferences: { theme: 'light', high_contrast: false, locale: 'en' },
      ...overrides.user,
    },
    companies: company ? [company] : [],
    current_company: company,
    permissions: overrides.permissions ?? [],
    branches: [],
  };
}

export interface RenderWithProvidersOptions extends Omit<RenderOptions, 'wrapper'> {
  /** Preloads the session slice (status → authenticated). */
  session?: SessionPayload;
  store?: AppStore;
  queryClient?: QueryClient;
}

export function makeTestQueryClient() {
  return new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity }, mutations: { retry: false } },
  });
}

export function renderWithProviders(ui: ReactElement, { session, store = makeStore(), queryClient = makeTestQueryClient(), ...options }: RenderWithProvidersOptions = {}) {
  if (session) store.dispatch(sessionResolved(session));

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <ReduxProvider store={store}>
        <QueryClientProvider client={queryClient}>
          <MotionConfig reducedMotion="always">
            <ToastProvider>{children}</ToastProvider>
          </MotionConfig>
        </QueryClientProvider>
      </ReduxProvider>
    );
  }

  return { store, queryClient, ...render(ui, { wrapper: Wrapper, ...options }) };
}
