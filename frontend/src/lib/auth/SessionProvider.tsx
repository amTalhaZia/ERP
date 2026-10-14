'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, type ReactNode } from 'react';
import { usePathname, useRouter } from 'next/navigation';
import { useQueryClient } from '@tanstack/react-query';
import { useAppDispatch, useAppSelector } from '@/store';
import { sessionCleared, sessionLoading, sessionResolved, selectSession } from '@/store/sessionSlice';
import { setTheme, setHighContrast } from '@/store/uiSlice';
import { setApiCompanyId, setUnauthorizedHandler } from '@/lib/api/client';
import { authApi } from '@/features/auth/api';
import type { SessionPayload } from '@/features/auth/types';

interface SessionContextValue {
  refresh: () => Promise<SessionPayload | null>;
  applySession: (s: SessionPayload) => void;
  logout: () => Promise<void>;
  switchCompany: (companyId: number) => Promise<void>;
}

const SessionContext = createContext<SessionContextValue | null>(null);

const PUBLIC_PREFIXES = ['/login', '/forgot-password', '/reset-password', '/verify-email', '/two-factor'];
export const isPublicPath = (p: string) => PUBLIC_PREFIXES.some((x) => p === x || p.startsWith(x + '/'));

export function SessionProvider({ children }: { children: ReactNode }) {
  const dispatch = useAppDispatch();
  const session = useAppSelector(selectSession);
  const router = useRouter();
  const pathname = usePathname();
  const queryClient = useQueryClient();

  const applySession = useCallback(
    (s: SessionPayload) => {
      setApiCompanyId(s.current_company?.id ?? null);
      dispatch(sessionResolved(s));
      // Sync server-side preference into UI state (server wins on login).
      dispatch(setTheme(s.user.preferences.theme));
      dispatch(setHighContrast(s.user.preferences.high_contrast));
    },
    [dispatch],
  );

  const refresh = useCallback(async () => {
    dispatch(sessionLoading());
    try {
      const { data } = await authApi.session();
      applySession(data);
      return data;
    } catch {
      setApiCompanyId(null);
      dispatch(sessionCleared());
      return null;
    }
  }, [applySession, dispatch]);

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } finally {
      setApiCompanyId(null);
      dispatch(sessionCleared());
      queryClient.clear();
      router.replace('/login');
    }
  }, [dispatch, queryClient, router]);

  const switchCompany = useCallback(
    async (companyId: number) => {
      const { data } = await authApi.switchCompany(companyId);
      applySession(data);
      // All cached server data belongs to the previous tenant.
      queryClient.clear();
      router.replace('/dashboard');
    },
    [applySession, queryClient, router],
  );

  // Bootstrap the session once.
  useEffect(() => {
    if (session.status === 'unknown') void refresh();
  }, [session.status, refresh]);

  // Any 401 from the API means the cookie session died → go to login.
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setApiCompanyId(null);
      dispatch(sessionCleared());
      queryClient.clear();
      if (!isPublicPath(window.location.pathname)) {
        const next = encodeURIComponent(window.location.pathname + window.location.search);
        router.replace(`/login?next=${next}`);
      }
    });
    return () => setUnauthorizedHandler(null);
  }, [dispatch, queryClient, router]);

  // Route guarding (client-side; the API is the real gate).
  useEffect(() => {
    if (session.status === 'guest' && !isPublicPath(pathname)) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
    if (session.status === 'authenticated' && isPublicPath(pathname) && !pathname.startsWith('/two-factor')) {
      router.replace('/dashboard');
    }
  }, [session.status, pathname, router]);

  const value = useMemo(() => ({ refresh, applySession, logout, switchCompany }), [refresh, applySession, logout, switchCompany]);

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession() {
  const ctx = useContext(SessionContext);
  if (!ctx) throw new Error('useSession must be used within SessionProvider');
  return ctx;
}
