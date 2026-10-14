'use client';

import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Paginated, Single } from '@/lib/api/types';
import { useAppSelector } from '@/store';
import { useToast } from '@/components/ui';

/**
 * Typed hooks for a REST resource under /api/v1. Every query key starts with
 * the company id so switching company never shows another tenant's cache.
 *
 *   const employees = resource<Employee, EmployeeInput>('employees');
 *   const list = employees.useList(apiParams);
 *   const create = employees.useCreate({ onSuccess: … });
 */
export function resource<T extends { id: number }, TInput = Partial<T>>(path: string, label = 'Record') {
  const useCompanyKey = () => useAppSelector((s) => s.session.current_company?.id ?? 0);

  return {
    path,
    useList(params: Record<string, unknown> = {}, opts: { enabled?: boolean } = {}) {
      const companyId = useCompanyKey();
      return useQuery({
        queryKey: [companyId, path, 'list', params],
        queryFn: () => get<Paginated<T>>(`/${path}`, params),
        placeholderData: keepPreviousData,
        enabled: opts.enabled ?? true,
      });
    },
    useAll<R = T>(subpath = 'all', params: Record<string, unknown> = {}, opts: { enabled?: boolean } = {}) {
      const companyId = useCompanyKey();
      return useQuery({
        queryKey: [companyId, path, subpath, params],
        queryFn: () => get<{ data: R[] }>(`/${path}/${subpath}`, params).then((r) => r.data),
        staleTime: 60_000,
        enabled: opts.enabled ?? true,
      });
    },
    useOne(id: number | string | undefined, opts: { enabled?: boolean } = {}) {
      const companyId = useCompanyKey();
      return useQuery({
        queryKey: [companyId, path, 'one', id],
        queryFn: () => get<Single<T>>(`/${path}/${id}`).then((r) => r.data),
        enabled: (opts.enabled ?? true) && id !== undefined && id !== 'new',
      });
    },
    useCreate(opts: { onSuccess?: (row: T) => void; silent?: boolean } = {}) {
      const qc = useQueryClient();
      const toast = useToast();
      return useMutation<T, ApiError, TInput>({
        mutationFn: (body) => api.post<Single<T>>(`/${path}`, body).then((r) => r.data.data),
        onSuccess: (row) => {
          void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(path) });
          if (!opts.silent) toast.success(`${label} created`);
          opts.onSuccess?.(row);
        },
        onError: (e) => {
          if (e.status !== 422) toast.error(`Could not create ${label.toLowerCase()}`, e.message);
        },
      });
    },
    useUpdate(opts: { onSuccess?: (row: T) => void; silent?: boolean } = {}) {
      const qc = useQueryClient();
      const toast = useToast();
      return useMutation<T, ApiError, { id: number; body: TInput }>({
        mutationFn: ({ id, body }) => api.put<Single<T>>(`/${path}/${id}`, body).then((r) => r.data.data),
        onSuccess: (row) => {
          void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(path) });
          if (!opts.silent) toast.success(`${label} saved`);
          opts.onSuccess?.(row);
        },
        onError: (e) => {
          if (e.status !== 422) toast.error(`Could not save ${label.toLowerCase()}`, e.message);
        },
      });
    },
    useDelete(opts: { onSuccess?: () => void } = {}) {
      const qc = useQueryClient();
      const toast = useToast();
      return useMutation<void, ApiError, number>({
        mutationFn: (id) => api.delete(`/${path}/${id}`).then(() => undefined),
        onSuccess: () => {
          void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(path) });
          toast.success(`${label} deleted`);
          opts.onSuccess?.();
        },
        onError: (e) => toast.error(`Could not delete ${label.toLowerCase()}`, e.message),
      });
    },
    /** POST /{path}/{id}/{action} — approve, post, cancel, … */
    useAction<TBody = Record<string, unknown>, TRes = T>(action: string, opts: { success?: string; onSuccess?: (r: TRes) => void } = {}) {
      const qc = useQueryClient();
      const toast = useToast();
      return useMutation<TRes, ApiError, { id: number; body?: TBody }>({
        mutationFn: ({ id, body }) => api.post<Single<TRes>>(`/${path}/${id}/${action}`, body ?? {}).then((r) => r.data?.data ?? (r.data as unknown as TRes)),
        onSuccess: (r) => {
          void qc.invalidateQueries({ predicate: (q) => q.queryKey.includes(path) });
          toast.success(opts.success ?? `${label} updated`);
          opts.onSuccess?.(r);
        },
        onError: (e) => {
          if (e.status !== 422) toast.error('Action failed', e.message);
        },
      });
    },
  };
}

/** Apply a 422 ApiError to react-hook-form fields; returns leftover message if none matched. */
export function applyServerErrors(
  e: ApiError,
  setError: (name: never, err: { type: string; message: string }) => void,
  known?: string[],
): string | null {
  if (e.status !== 422 || !e.errors) return e.message;
  let matched = false;
  for (const [field, messages] of Object.entries(e.errors)) {
    if (!known || known.includes(field) || known.includes(field.split('.')[0]!)) {
      setError(field as never, { type: 'server', message: messages[0] ?? 'Invalid' });
      matched = true;
    }
  }
  return matched ? null : Object.values(e.errors)[0]?.[0] ?? e.message;
}
