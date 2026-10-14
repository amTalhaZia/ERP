'use client';

import { useQuery } from '@tanstack/react-query';
import { StatusBadge, type BadgeVariant, type SelectOption } from '@/components/ui';
import { useCan } from '@/lib/auth/useCan';
import { humanize } from '@/lib/format';
import type { ApiError } from '@/lib/api/errors';
import { useAppSelector } from '@/store';
import { selectUser } from '@/store/sessionSlice';
import { salesApi, type CustomerRef, type UserRef } from './api';

// ---------------------------------------------------------------------------
// Status badges
// ---------------------------------------------------------------------------

const VARIANTS: Record<string, BadgeVariant> = {
  // quotations
  draft: 'neutral',
  sent: 'info',
  accepted: 'success',
  rejected: 'danger',
  expired: 'warning',
  converted: 'primary',
  // sales orders
  confirmed: 'info',
  approved: 'success',
  partially_delivered: 'warning',
  delivered: 'success',
  cancelled: 'neutral',
  // deliveries
  pending: 'warning',
  in_transit: 'info',
  // leads
  new: 'info',
  contacted: 'primary',
  qualified: 'success',
  unqualified: 'neutral',
  // opportunities
  open: 'info',
  won: 'success',
  lost: 'danger',
};

export function SalesStatus({ status, size }: { status: string; size?: 'sm' | 'md' }) {
  return <StatusBadge status={status} variant={VARIANTS[status] ?? 'neutral'} label={humanize(status)} size={size} />;
}

export const enumOptions = (values: readonly string[]): SelectOption[] => values.map((v) => ({ value: v, label: humanize(v) }));

// ---------------------------------------------------------------------------
// Pickers backed by other modules
// ---------------------------------------------------------------------------

/**
 * Company users for rep/assignee/owner pickers. GET /users needs `users.view`;
 * without it we fall back to the current user plus any users already on the
 * record (`extra`) so existing values still render.
 */
export function useUserOptions(extra: (UserRef | null | undefined)[] = []) {
  const can = useCan();
  const me = useAppSelector(selectUser);
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const allowed = can('users.view');
  const q = useQuery({ queryKey: [companyId, 'users', 'sales-picker'], queryFn: salesApi.users, enabled: allowed, staleTime: 5 * 60_000 });
  const map = new Map<number, string>();
  for (const u of q.data ?? []) map.set(u.id, u.name);
  if (me) map.set(me.id, map.get(me.id) ?? me.name);
  for (const u of extra) if (u) map.set(u.id, map.get(u.id) ?? u.name);
  const options: SelectOption[] = [...map.entries()].sort((a, b) => a[1].localeCompare(b[1])).map(([id, name]) => ({ value: String(id), label: name }));
  return { options, isLoading: q.isLoading && allowed, limited: !allowed };
}

/** Active customers `{id, name, code}`; includes `current` even if inactive. */
export function useCustomerOptions(current?: CustomerRef | null) {
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const q = useQuery({ queryKey: [companyId, 'customers', 'all'], queryFn: salesApi.customersAll, staleTime: 60_000 });
  const rows = [...(q.data ?? [])];
  if (current && !rows.some((r) => r.id === current.id)) rows.unshift(current);
  const options: SelectOption[] = rows.map((c) => ({ value: String(c.id), label: `${c.name} · ${c.code}` }));
  return { options, rows, isLoading: q.isLoading };
}

export function useWarehouseOptions() {
  const can = useCan();
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  const allowed = can('warehouses.view');
  const q = useQuery({ queryKey: [companyId, 'warehouses', 'sales-picker'], queryFn: salesApi.warehouses, enabled: allowed, staleTime: 5 * 60_000 });
  const rows = q.data ?? [];
  return {
    options: rows.map((w) => ({ value: String(w.id), label: `${w.name} · ${w.code}` })) as SelectOption[],
    rows,
    allowed,
    isLoading: q.isLoading && allowed,
  };
}

export function useStages() {
  const companyId = useAppSelector((s) => s.session.current_company?.id ?? 0);
  return useQuery({ queryKey: [companyId, 'pipeline-stages', 'all'], queryFn: salesApi.stages, staleTime: 60_000 });
}

// ---------------------------------------------------------------------------
// Errors
// ---------------------------------------------------------------------------

/** Flattens a 422 into `{field: message}` (incl. `lines.0.quantity`); returns a banner message. */
export function flattenErrors(e: ApiError): { fields: Record<string, string>; message: string } {
  const fields: Record<string, string> = {};
  if (e.status === 422 && e.errors) {
    for (const [k, v] of Object.entries(e.errors)) fields[k] = v[0] ?? 'Invalid';
    return { fields, message: 'Please correct the highlighted fields.' };
  }
  return { fields, message: e.message };
}

/** "" → null, trimmed otherwise. */
export const nullable = (v: string | null | undefined) => {
  const t = (v ?? '').trim();
  return t === '' ? null : t;
};
