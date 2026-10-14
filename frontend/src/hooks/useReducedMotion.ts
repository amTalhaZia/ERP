'use client';

/**
 * Re-export of framer-motion's `useReducedMotion` so UI code has a single
 * import path. Returns `true` when the OS asks for reduced motion
 * (`null` before hydration — treat as "unknown", i.e. falsy).
 */
export { useReducedMotion } from 'framer-motion';
