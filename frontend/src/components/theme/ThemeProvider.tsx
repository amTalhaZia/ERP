'use client';

import { useEffect, type ReactNode } from 'react';
import { useAppDispatch, useAppSelector } from '@/store';
import { hydrateUi, type ThemeMode } from '@/store/uiSlice';

const THEME_KEY = 'erp.theme';
const CONTRAST_KEY = 'erp.contrast';
const SIDEBAR_KEY = 'erp.sidebar';

function safeGet(key: string) {
  try {
    return localStorage.getItem(key);
  } catch {
    return null;
  }
}
function safeSet(key: string, value: string) {
  try {
    localStorage.setItem(key, value);
  } catch {
    /* private mode / blocked storage */
  }
}

/**
 * Applies theme / contrast / sidebar preferences to <html> and persists them.
 * These are per-device conveniences; the server also stores the user's
 * preference and the SessionProvider syncs it on login.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  const dispatch = useAppDispatch();
  const theme = useAppSelector((s) => s.ui.theme);
  const highContrast = useAppSelector((s) => s.ui.highContrast);
  const sidebarCollapsed = useAppSelector((s) => s.ui.sidebarCollapsed);

  // Hydrate once from localStorage.
  useEffect(() => {
    const t = safeGet(THEME_KEY) as ThemeMode | null;
    const c = safeGet(CONTRAST_KEY);
    const s = safeGet(SIDEBAR_KEY);
    dispatch(
      hydrateUi({
        ...(t === 'light' || t === 'dark' || t === 'system' ? { theme: t } : {}),
        ...(c ? { highContrast: c === 'high' } : {}),
        ...(s ? { sidebarCollapsed: s === '1' } : {}),
      }),
    );
  }, [dispatch]);

  useEffect(() => {
    const root = document.documentElement;
    if (theme === 'system') root.removeAttribute('data-theme');
    else root.setAttribute('data-theme', theme);
    safeSet(THEME_KEY, theme);
  }, [theme]);

  useEffect(() => {
    const root = document.documentElement;
    if (highContrast) root.setAttribute('data-contrast', 'high');
    else root.removeAttribute('data-contrast');
    safeSet(CONTRAST_KEY, highContrast ? 'high' : 'normal');
  }, [highContrast]);

  useEffect(() => {
    safeSet(SIDEBAR_KEY, sidebarCollapsed ? '1' : '0');
  }, [sidebarCollapsed]);

  return <>{children}</>;
}
