'use client';

import { useEffect, useRef } from 'react';

/**
 * Stack of open overlays (modals, drawers). Only the top-most one reacts to
 * Escape, so a ConfirmDialog opened from inside a Drawer closes alone.
 */
const stack: symbol[] = [];

export function useEscapeToClose(open: boolean, enabled: boolean, onClose: () => void): void {
  const latest = useRef({ enabled, onClose });
  useEffect(() => {
    latest.current = { enabled, onClose };
  });

  useEffect(() => {
    if (!open) return;
    const token = Symbol('overlay');
    stack.push(token);
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key !== 'Escape' || event.defaultPrevented) return;
      if (stack[stack.length - 1] !== token) return;
      if (!latest.current.enabled) return;
      event.preventDefault();
      latest.current.onClose();
    };
    document.addEventListener('keydown', onKeyDown);
    return () => {
      const i = stack.indexOf(token);
      if (i >= 0) stack.splice(i, 1);
      document.removeEventListener('keydown', onKeyDown);
    };
  }, [open]);
}

export const MOTION = {
  duration: 0.18,
  slow: 0.22,
  exit: 0.14,
  ease: [0.2, 0, 0, 1] as const,
};
