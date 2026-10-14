'use client';

import { useAppSelector } from '@/store';
import { DEFAULT_LOCALE, EM_DASH } from '@/lib/format';

/** The company's IANA time zone (attendance timestamps are shown in it). */
export const useCompanyTz = () => useAppSelector((s) => s.session.current_company?.timezone || 'UTC');

function safeTz(tz: string) {
  try {
    new Intl.DateTimeFormat(DEFAULT_LOCALE, { timeZone: tz });
    return tz;
  } catch {
    return 'UTC';
  }
}

/** `HH:mm` of an ISO instant in the given zone. */
export function formatTimeTz(iso: string | null | undefined, tz: string): string {
  if (!iso) return EM_DASH;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return EM_DASH;
  return new Intl.DateTimeFormat(DEFAULT_LOCALE, { timeZone: safeTz(tz), hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(d);
}

export function formatDateTimeTz(iso: string | null | undefined, tz: string): string {
  if (!iso) return EM_DASH;
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return EM_DASH;
  return new Intl.DateTimeFormat(DEFAULT_LOCALE, { timeZone: safeTz(tz), day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(d);
}

function parts(d: Date, tz: string) {
  const p = new Intl.DateTimeFormat('en-CA', {
    timeZone: safeTz(tz), year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(d);
  const get = (t: string) => Number(p.find((x) => x.type === t)?.value ?? 0);
  return { y: get('year'), m: get('month'), d: get('day'), h: get('hour') % 24, mi: get('minute'), s: get('second') };
}

/** Today's date (yyyy-MM-dd) in the given zone. */
export function todayInTz(tz: string, now: Date = new Date()): string {
  const p = parts(now, tz);
  return `${p.y}-${String(p.m).padStart(2, '0')}-${String(p.d).padStart(2, '0')}`;
}

/** Wall-clock `HH:mm` in the zone for an ISO instant (for prefilling time inputs). */
export function timeInputTz(iso: string | null | undefined, tz: string): string {
  if (!iso) return '';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const p = parts(d, tz);
  return `${String(p.h).padStart(2, '0')}:${String(p.mi).padStart(2, '0')}`;
}

/** Converts a wall-clock date + `HH:mm` in `tz` into a UTC ISO instant. */
export function zonedToIso(date: string, time: string, tz: string): string | null {
  const dm = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date);
  const tm = /^(\d{1,2}):(\d{2})$/.exec(time);
  if (!dm || !tm) return null;
  const guess = Date.UTC(Number(dm[1]), Number(dm[2]) - 1, Number(dm[3]), Number(tm[1]), Number(tm[2]));
  const offset = (ms: number) => {
    const p = parts(new Date(ms), tz);
    return Date.UTC(p.y, p.m - 1, p.d, p.h, p.mi, p.s) - ms;
  };
  let ts = guess - offset(guess);
  ts = guess - offset(ts); // second pass settles DST edges
  return new Date(ts).toISOString();
}

export function minutesLabel(min: number | null | undefined): string {
  if (min === null || min === undefined) return EM_DASH;
  if (min === 0) return '0m';
  const h = Math.floor(min / 60);
  const m = min % 60;
  return h ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`;
}
