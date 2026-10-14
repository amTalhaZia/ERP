'use client';

import { useCallback, useSyncExternalStore } from 'react';

/**
 * Subscribes to a CSS media query. SSR-safe: returns `serverValue` during
 * server render and the first client render (no hydration mismatch).
 */
export function useMediaQuery(query: string, serverValue = false): boolean {
  const subscribe = useCallback(
    (onChange: () => void) => {
      const mql = window.matchMedia(query);
      mql.addEventListener('change', onChange);
      return () => mql.removeEventListener('change', onChange);
    },
    [query],
  );
  return useSyncExternalStore(
    subscribe,
    () => window.matchMedia(query).matches,
    () => serverValue,
  );
}

/** Mirrors the SCSS `down(md)` breakpoint (< 768px). */
export const MOBILE_QUERY = '(max-width: 767.98px)';

export function useIsMobile(): boolean {
  return useMediaQuery(MOBILE_QUERY);
}

/** True once the component has mounted on the client. SSR-safe (no effect/setState). */
export function useIsMounted(): boolean {
  return useSyncExternalStore(
    () => () => {},
    () => true,
    () => false,
  );
}
