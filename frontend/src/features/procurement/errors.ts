import type { ApiError } from '@/lib/api/errors';

export interface SplitErrors {
  /** Header-level field → message. */
  header: Record<string, string>;
  /** Row key → field → message (from `lines.N.field`). */
  rows: Record<number, Record<string, string>>;
  /** Banner text: 409/other message, or the first 422 message. */
  message: string | null;
}

export const NO_ERRORS: SplitErrors = { header: {}, rows: {}, message: null };

/**
 * Map a server error onto a lines document. `rowKeys[i]` is the client row key
 * that was sent as payload index i, so `lines.3.quantity` lands on the right row
 * even when blank rows were dropped before sending.
 */
export function splitServerErrors(e: ApiError, rowKeys: number[] = []): SplitErrors {
  if (e.status !== 422 || !e.errors) return { header: {}, rows: {}, message: e.message };
  const header: Record<string, string> = {};
  const rows: Record<number, Record<string, string>> = {};
  for (const [field, messages] of Object.entries(e.errors)) {
    const msg = messages[0] ?? 'Invalid';
    const m = /^lines\.(\d+)\.(.+)$/.exec(field);
    if (m) {
      const key = rowKeys[Number(m[1])];
      if (key !== undefined) {
        rows[key] = { ...(rows[key] ?? {}), [m[2]!]: msg };
        continue;
      }
    }
    header[field] = msg;
  }
  const first = Object.values(e.errors)[0]?.[0] ?? e.message;
  return { header, rows, message: Object.keys(rows).length ? 'Some lines need attention — see the highlighted rows.' : first };
}
