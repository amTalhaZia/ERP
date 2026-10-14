'use client';

import { useEffect, useRef, useState } from 'react';
import { useReducedMotion } from 'framer-motion';

export interface CountUpOptions {
  /** Animation duration in ms. */
  duration?: number;
  /** Set false to disable (returns target immediately). */
  enabled?: boolean;
}

const easeOutCubic = (t: number) => 1 - Math.pow(1 - t, 3);

/**
 * Animates from the previous value to `target` using requestAnimationFrame.
 * Disabled automatically when the user prefers reduced motion.
 */
export function useCountUp(target: number, { duration = 700, enabled = true }: CountUpOptions = {}): number {
  const reduced = useReducedMotion();
  const animate = enabled && !reduced && Number.isFinite(target);
  const [animated, setAnimated] = useState(target);
  const fromRef = useRef(target);

  useEffect(() => {
    if (!animate) return;
    const from = fromRef.current;
    if (from === target) return;
    let frame = 0;
    const start = performance.now();
    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration);
      const next = from + (target - from) * easeOutCubic(t);
      setAnimated(t < 1 ? next : target);
      if (t < 1) frame = requestAnimationFrame(tick);
      else fromRef.current = target;
    };
    frame = requestAnimationFrame(tick);
    return () => {
      cancelAnimationFrame(frame);
      fromRef.current = target;
    };
  }, [target, duration, animate]);

  return animate ? animated : target;
}
