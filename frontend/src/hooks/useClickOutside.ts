'use client';

import { useEffect, useRef, type RefObject } from 'react';

type MaybeRef = RefObject<HTMLElement | null>;

/**
 * Calls `handler` when a pointer-down lands outside every element in `refs`.
 * Uses `pointerdown` so it fires before focus moves (menus close reliably).
 */
export function useClickOutside(refs: MaybeRef | MaybeRef[], handler: (event: PointerEvent) => void, enabled = true): void {
  // Latest-value ref: the listener always sees the current refs/handler without re-binding.
  const latest = useRef({ refs: [] as MaybeRef[], handler });
  useEffect(() => {
    latest.current = { refs: Array.isArray(refs) ? refs : [refs], handler };
  });

  useEffect(() => {
    if (!enabled) return;
    const onPointerDown = (event: PointerEvent) => {
      const target = event.target as Node | null;
      if (!target) return;
      const inside = latest.current.refs.some((r) => r.current?.contains(target));
      if (!inside) latest.current.handler(event);
    };
    document.addEventListener('pointerdown', onPointerDown, true);
    return () => document.removeEventListener('pointerdown', onPointerDown, true);
  }, [enabled]);
}
