'use client';

import { useCallback, useMemo } from 'react';
import { usePathname, useRouter, useSearchParams } from 'next/navigation';

export interface ListState {
  page: number;
  perPage: number;
  search: string;
  sort: string | undefined;
  filters: Record<string, string>;
  from?: string;
  to?: string;
}

/**
 * List state lives in the URL (?page=2&search=x&sort=-name&f.status=active)
 * so lists are shareable, survive refresh, and the back button works.
 */
export function useListState(defaults: { sort?: string; perPage?: number } = {}) {
  const params = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();

  const state: ListState = useMemo(() => {
    const filters: Record<string, string> = {};
    params.forEach((v, k) => {
      if (k.startsWith('f.') && v !== '') filters[k.slice(2)] = v;
    });
    return {
      page: Math.max(1, Number(params.get('page') ?? 1) || 1),
      perPage: Number(params.get('per_page') ?? defaults.perPage ?? 25) || 25,
      search: params.get('search') ?? '',
      sort: params.get('sort') ?? defaults.sort,
      filters,
      from: params.get('from') ?? undefined,
      to: params.get('to') ?? undefined,
    };
  }, [params, defaults.sort, defaults.perPage]);

  const update = useCallback(
    (patch: Partial<Omit<ListState, 'filters'>> & { filters?: Record<string, string | undefined> }, resetPage = true) => {
      const next = new URLSearchParams(params.toString());
      const set = (k: string, v: string | number | undefined | null) => {
        if (v === undefined || v === null || v === '') next.delete(k);
        else next.set(k, String(v));
      };
      if ('page' in patch) set('page', patch.page === 1 ? undefined : patch.page);
      else if (resetPage) next.delete('page');
      if ('perPage' in patch) set('per_page', patch.perPage);
      if ('search' in patch) set('search', patch.search);
      if ('sort' in patch) set('sort', patch.sort);
      if ('from' in patch) set('from', patch.from);
      if ('to' in patch) set('to', patch.to);
      if (patch.filters) {
        for (const [k, v] of Object.entries(patch.filters)) set(`f.${k}`, v);
      }
      const qs = next.toString();
      router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
    },
    [params, pathname, router],
  );

  const clearFilters = useCallback(() => {
    const next = new URLSearchParams(params.toString());
    [...next.keys()].forEach((k) => {
      if (k.startsWith('f.') || k === 'from' || k === 'to') next.delete(k);
    });
    next.delete('page');
    const qs = next.toString();
    router.replace(qs ? `${pathname}?${qs}` : pathname, { scroll: false });
  }, [params, pathname, router]);

  /** Query params in the API's vocabulary. */
  const apiParams = useMemo(() => {
    const p: Record<string, string | number> = { page: state.page, per_page: state.perPage };
    if (state.search) p.search = state.search;
    if (state.sort) p.sort = state.sort;
    if (state.from) p.from = state.from;
    if (state.to) p.to = state.to;
    for (const [k, v] of Object.entries(state.filters)) p[`filter[${k}]`] = v;
    return p;
  }, [state]);

  const activeFilterCount = Object.keys(state.filters).length + (state.from ? 1 : 0) + (state.to ? 1 : 0);

  return { state, update, clearFilters, apiParams, activeFilterCount };
}
