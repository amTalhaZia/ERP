'use client';

import { useEffect, useMemo, useRef, useState } from 'react';

/** Returns a copy of `value` that only updates after `delay` ms of inactivity. */
export function useDebounce<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value, delay]);
  return debounced;
}

export interface DebouncedFn<TArgs extends unknown[]> {
  (...args: TArgs): void;
  /** Drop any pending call. */
  cancel: () => void;
  /** Run a pending call immediately. */
  flush: () => void;
}

/**
 * Returns a stable debounced wrapper around `fn`. The latest `fn` is always
 * invoked (no stale closures); pending calls are cancelled on unmount.
 */
export function useDebouncedCallback<TArgs extends unknown[]>(fn: (...args: TArgs) => void, delay = 300): DebouncedFn<TArgs> {
  const fnRef = useRef(fn);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pending = useRef<TArgs | null>(null);

  useEffect(() => {
    fnRef.current = fn;
  }, [fn]);

  const debounced = useMemo(() => {
    const wrapped = ((...args: TArgs) => {
      pending.current = args;
      if (timer.current) clearTimeout(timer.current);
      timer.current = setTimeout(() => {
        timer.current = null;
        const a = pending.current;
        pending.current = null;
        if (a) fnRef.current(...a);
      }, delay);
    }) as DebouncedFn<TArgs>;

    wrapped.cancel = () => {
      if (timer.current) clearTimeout(timer.current);
      timer.current = null;
      pending.current = null;
    };

    wrapped.flush = () => {
      if (timer.current && pending.current) {
        clearTimeout(timer.current);
        const a = pending.current;
        timer.current = null;
        pending.current = null;
        fnRef.current(...a);
      }
    };

    return wrapped;
  }, [delay]);

  useEffect(() => () => debounced.cancel(), [debounced]);

  return debounced;
}
