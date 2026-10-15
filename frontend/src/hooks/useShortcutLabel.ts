'use client';

import { useSyncExternalStore } from 'react';

interface NavigatorUAData {
  platform?: string;
}

/** Apple platforms use ⌘ as the primary modifier; everything else uses Ctrl. */
export function isApplePlatform(): boolean {
  if (typeof navigator === 'undefined') return false;
  const uaPlatform = (navigator as Navigator & { userAgentData?: NavigatorUAData }).userAgentData?.platform;
  const platform = uaPlatform || navigator.platform || navigator.userAgent;
  return /mac|iphone|ipad|ipod/i.test(platform);
}

const noopSubscribe = () => () => {};

/** "⌘K" on macOS/iOS, "Ctrl K" elsewhere. SSR renders "Ctrl K" (no effect/setState). */
export function useModKeyLabel(key: string): string {
  const apple = useSyncExternalStore(noopSubscribe, isApplePlatform, () => false);
  return apple ? `⌘${key}` : `Ctrl ${key}`;
}

/** True when a keyboard event originates from a text-entry control. */
export function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false;
  if (target.isContentEditable) return true;
  const tag = target.tagName;
  if (tag === 'TEXTAREA' || tag === 'SELECT') return true;
  if (tag === 'INPUT') {
    const type = (target as HTMLInputElement).type;
    return !['checkbox', 'radio', 'button', 'submit', 'reset', 'range', 'color', 'file'].includes(type);
  }
  return false;
}
