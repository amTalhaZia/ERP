import { describe, expect, it } from 'vitest';
import { renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { Provider } from 'react-redux';
import { makeStore } from '@/store';
import { sessionResolved } from '@/store/sessionSlice';
import { useCan } from '@/lib/auth/useCan';
import { makeSession } from '@/test/render';

function setup(session: Parameters<typeof makeSession>[0]) {
  const store = makeStore();
  store.dispatch(sessionResolved(makeSession(session)));
  const wrapper = ({ children }: { children: ReactNode }) => <Provider store={store}>{children}</Provider>;
  return renderHook(() => useCan(), { wrapper }).result.current;
}

describe('useCan', () => {
  it('grants company permissions from the session', () => {
    const can = setup({ permissions: ['invoices.view', 'leave.request'] });
    expect(can('invoices.view')).toBe(true);
    expect(can('invoices.approve')).toBe(false);
    expect(can(['invoices.view', 'invoices.approve'])).toBe(false);
    expect(can(['invoices.view', 'invoices.approve'], 'any')).toBe(true);
    expect(can()).toBe(true);
  });

  it('denies platform permissions to non-platform users', () => {
    const can = setup({ permissions: ['dashboard.view'] });
    expect(can('platform.companies.manage')).toBe(false);
    expect(can('platform.system.view')).toBe(false);
  });

  it('grants platform permissions to platform admins without implying company permissions', () => {
    const can = setup({ permissions: [], user: { is_platform_admin: true, platform_role: 'platform.admin' } });
    expect(can('platform.companies.manage')).toBe(true);
    expect(can(['platform.users.manage', 'platform.system.view'])).toBe(true);
    expect(can('invoices.view')).toBe(false);
    expect(can(['platform.users.manage', 'payroll.view'])).toBe(false);
  });
});
