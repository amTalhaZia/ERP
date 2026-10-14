'use client';

import type { ReactNode } from 'react';
import type { UseQueryResult } from '@tanstack/react-query';
import { Inbox } from 'lucide-react';
import {
  DataTable, EmptyState, FilterBar, PageHeader, SearchInput, paginationFromMeta,
  type DataTableColumn, type DataTableProps,
} from '@/components/ui';
import type { Paginated } from '@/lib/api/types';
import type { useListState } from '@/lib/crud/useListState';
import styles from './crud.module.scss';

type ListStateApi = ReturnType<typeof useListState>;

/**
 * Standard list page: header + actions, search, filters (drawer on mobile),
 * server-paginated/sorted DataTable with loading/empty/error states.
 */
export function ListPage<T extends { id: number }>({
  title,
  description,
  actions,
  query,
  columns,
  list,
  searchPlaceholder = 'Search…',
  filters,
  emptyTitle = 'Nothing here yet',
  emptyDescription,
  emptyAction,
  onRowClick,
  rowActions,
  bulkActions,
  enableRowSelection,
  above,
  ariaLabel,
}: {
  title: string;
  description?: ReactNode;
  actions?: ReactNode;
  query: UseQueryResult<Paginated<T>>;
  columns: DataTableColumn<T>[];
  list: ListStateApi;
  searchPlaceholder?: string;
  filters?: ReactNode;
  emptyTitle?: string;
  emptyDescription?: ReactNode;
  emptyAction?: ReactNode;
  onRowClick?: (row: T) => void;
  rowActions?: DataTableProps<T>['rowActions'];
  bulkActions?: DataTableProps<T>['bulkActions'];
  enableRowSelection?: boolean;
  above?: ReactNode;
  ariaLabel?: string;
}) {
  const { state, update, clearFilters, activeFilterCount } = list;
  const meta = query.data?.meta?.pagination;
  const filtered = !!state.search || activeFilterCount > 0;

  return (
    <div className={styles.page}>
      <PageHeader title={title} description={description} actions={actions} />
      {above}
      <div className={styles.toolbar}>
        <SearchInput
          className={styles.search}
          placeholder={searchPlaceholder}
          defaultValue={state.search}
          onDebouncedChange={(v: string) => update({ search: v })}
          aria-label={searchPlaceholder}
        />
        {filters && (
          <FilterBar activeCount={activeFilterCount} onClear={clearFilters}>
            {filters}
          </FilterBar>
        )}
      </div>
      <DataTable<T>
        aria-label={ariaLabel ?? title}
        columns={columns}
        data={query.data?.data ?? []}
        isLoading={query.isLoading || (query.isFetching && query.isPlaceholderData)}
        error={query.error}
        onRetry={() => void query.refetch()}
        sort={state.sort}
        onSortChange={(sort) => update({ sort })}
        pagination={
          meta
            ? {
                ...paginationFromMeta(meta),
                onPageChange: (page: number) => update({ page }, false),
                onPerPageChange: (perPage: number) => update({ perPage }),
              }
            : undefined
        }
        onRowClick={onRowClick ? (row: T) => onRowClick(row) : undefined}
        rowActions={rowActions}
        bulkActions={bulkActions}
        enableRowSelection={enableRowSelection}
        enableColumnVisibility
        emptyState={
          filtered ? (
            <EmptyState icon={<Inbox size={22} />} title="No matching records" description="Try a different search or clear the filters." action={<button type="button" className={styles.linkBtn} onClick={() => { update({ search: '' }); clearFilters(); }}>Clear search & filters</button>} />
          ) : (
            <EmptyState icon={<Inbox size={22} />} title={emptyTitle} description={emptyDescription} action={emptyAction} />
          )
        }
      />
    </div>
  );
}
