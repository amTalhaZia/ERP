'use client';

import { useCallback, type Ref, type RefCallback } from 'react';

export function assignRef<T>(ref: Ref<T> | undefined, value: T | null): void {
  if (!ref) return;
  if (typeof ref === 'function') {
    ref(value);
  } else {
    ref.current = value;
  }
}

/** Merges up to three refs (forwarded + local) into one stable callback ref. */
export function useMergedRefs<T>(a: Ref<T> | undefined, b?: Ref<T> | undefined, c?: Ref<T> | undefined): RefCallback<T> {
  return useCallback(
    (value: T | null) => {
      assignRef(a, value);
      assignRef(b, value);
      assignRef(c, value);
    },
    [a, b, c],
  );
}
