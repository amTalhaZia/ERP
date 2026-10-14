'use client';

import {
  useEffect,
  useMemo,
  useRef,
  useState,
  type CSSProperties,
  type KeyboardEvent,
  type MouseEvent,
  type ReactNode,
} from 'react';
import {
  columnVisibilityFeature,
  createColumnHelper,
  flexRender,
  metaHelper,
  rowSelectionFeature,
  rowSortingFeature,
  tableFeatures,
  useTable,
  type ColumnDef,
  type ColumnVisibilityState,
  type OnChangeFn,
  type Row,
  type RowData,
  type RowSelectionState,
  type SortingState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, Columns3, MoreHorizontal } from 'lucide-react';
import { cn } from '@/lib/cn';
import { Button } from './Button';
import { Checkbox } from './Checkbox';
import { EmptyState } from './EmptyState';
import { ErrorState, type ErrorStateProps } from './ErrorState';
import { IconButton } from './IconButton';
import { Menu, MenuItem, MenuLabel } from './Menu';
import { Pagination, type PaginationProps } from './Pagination';
import { SkeletonTable } from './Skeleton';
import { Spinner } from './Spinner';
import styles from './DataTable.module.scss';

// ---------------------------------------------------------------------------
// Column typing
// ---------------------------------------------------------------------------

/** Per-column presentation hints — set via `meta: { … }` on a column def. */
export interface DataTableColumnMeta {
  align?: 'left' | 'center' | 'right';
  /** Right-aligned with tabular numerals. Implies `align: 'right'`. */
  numeric?: boolean;
  width?: number | string;
  minWidth?: number | string;
  maxWidth?: number | string;
  /** Ellipsize overflowing cell text. */
  truncate?: boolean;
  /** Label for the column-visibility menu when `header` is not a plain string. */
  label?: string;
  /** API sort field when it differs from the column id (e.g. `customer.name`). */
  sortKey?: string;
  className?: string;
  headerClassName?: string;
}

export const dataTableFeatures = tableFeatures({
  rowSortingFeature,
  rowSelectionFeature,
  columnVisibilityFeature,
  columnMeta: metaHelper<DataTableColumnMeta>(),
});

export type DataTableFeatures = typeof dataTableFeatures;
export type DataTableColumn<TRow extends RowData, TValue = unknown> = ColumnDef<DataTableFeatures, TRow, TValue>;
export type DataTableRow<TRow extends RowData> = Row<DataTableFeatures, TRow>;

/** Typed column helper: `const col = createDataTableColumnHelper<Invoice>(); col.accessor('total', { … })`. */
export function createDataTableColumnHelper<TRow extends RowData>() {
  return createColumnHelper<DataTableFeatures, TRow>();
}

// ---------------------------------------------------------------------------
// Sort string <-> TanStack SortingState
// ---------------------------------------------------------------------------

/** `'-created_at'` → `[{ id: 'created_at', desc: true }]`. Comma-separated for multi-sort. */
export function sortToSortingState(sort: string | null | undefined, keyToId: Record<string, string> = {}): SortingState {
  if (!sort) return [];
  return sort
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => {
      const desc = s.startsWith('-');
      const key = desc ? s.slice(1) : s;
      return { id: keyToId[key] ?? key, desc };
    });
}

/** `[{ id: 'created_at', desc: true }]` → `'-created_at'`; `undefined` when empty. */
export function sortingStateToSort(state: SortingState, idToKey: Record<string, string> = {}): string | undefined {
  if (state.length === 0) return undefined;
  return state.map((s) => `${s.desc ? '-' : ''}${idToKey[s.id] ?? s.id}`).join(',');
}

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface BulkActionHelpers {
  /** Row ids currently selected (may include rows on other pages). */
  selectedIds: string[];
  clear: () => void;
}

export interface DataTableProps<TRow extends RowData> {
  columns: ReadonlyArray<DataTableColumn<TRow>>;
  data: ReadonlyArray<TRow> | undefined;
  /** Stable row id — defaults to `row.id` when present, else the index. Required for selection across pages. */
  getRowId?: (row: TRow, index: number) => string;

  /** Initial load → SkeletonTable; with existing data → dimmed table + spinner (keepPreviousData). */
  isLoading?: boolean;
  error?: ErrorStateProps['error'];
  onRetry?: () => void;
  /** Rendered when there are no rows. Defaults to a generic EmptyState. */
  emptyState?: ReactNode;

  /** Server-side pagination (see <Pagination/>). */
  pagination?: PaginationProps;

  /** Controlled sort string, e.g. `'-created_at'`. Columns opt in with `enableSorting: true`. */
  sort?: string;
  onSortChange?: (sort: string | undefined) => void;

  /** Row selection — controlled or uncontrolled. Providing `bulkActions` enables it. */
  enableRowSelection?: boolean | ((row: TRow) => boolean);
  rowSelection?: RowSelectionState;
  onRowSelectionChange?: (state: RowSelectionState) => void;
  /** Toolbar content shown while rows are selected. */
  bulkActions?: (selectedRows: TRow[], helpers: BulkActionHelpers) => ReactNode;

  onRowClick?: (row: TRow, event: MouseEvent<HTMLTableRowElement> | KeyboardEvent<HTMLTableRowElement>) => void;
  /** Return <MenuItem>s; rendered in a per-row "⋯" menu. */
  rowActions?: (row: TRow) => ReactNode;
  rowClassName?: (row: TRow) => string | undefined;

  /** Show the "Columns" visibility toggle. Columns opt out with `enableHiding: false`. */
  enableColumnVisibility?: boolean;
  columnVisibility?: ColumnVisibilityState;
  onColumnVisibilityChange?: (state: ColumnVisibilityState) => void;

  /** Left-side toolbar slot (search, filters). */
  toolbar?: ReactNode;
  /** Keep the header visible while the body scrolls. Requires `maxHeight` (defaults to 70vh). */
  stickyHeader?: boolean;
  maxHeight?: number | string;
  dense?: boolean;
  /** Accessible table name. */
  'aria-label'?: string;
  skeletonRows?: number;
  className?: string;
}

const SELECT_COLUMN_ID = '__select';
const ACTIONS_COLUMN_ID = '__actions';
const EMPTY: never[] = [];

function defaultGetRowId<TRow extends RowData>(row: TRow, index: number): string {
  const id = (row as Record<string, unknown>).id;
  return id !== undefined && id !== null ? String(id) : String(index);
}

function metaStyle(meta: DataTableColumnMeta | undefined): CSSProperties | undefined {
  if (!meta) return undefined;
  const { width, minWidth, maxWidth } = meta;
  if (width === undefined && minWidth === undefined && maxWidth === undefined) return undefined;
  return { width, minWidth, maxWidth };
}

// ---------------------------------------------------------------------------
// Component
// ---------------------------------------------------------------------------

export function DataTable<TRow extends RowData>({
  columns,
  data,
  getRowId = defaultGetRowId,
  isLoading = false,
  error,
  onRetry,
  emptyState,
  pagination,
  sort,
  onSortChange,
  enableRowSelection,
  rowSelection: rowSelectionProp,
  onRowSelectionChange: onRowSelectionChangeProp,
  bulkActions,
  onRowClick,
  rowActions,
  rowClassName,
  enableColumnVisibility = false,
  columnVisibility: columnVisibilityProp,
  onColumnVisibilityChange: onColumnVisibilityChangeProp,
  toolbar,
  stickyHeader = false,
  maxHeight,
  dense = false,
  'aria-label': ariaLabel,
  skeletonRows = 8,
  className,
}: DataTableProps<TRow>) {
  const selectionEnabled = enableRowSelection !== undefined ? enableRowSelection !== false : !!(bulkActions || rowSelectionProp || onRowSelectionChangeProp);

  // ----- Columns (prepend selection, append actions) ------------------------
  const allColumns = useMemo<ReadonlyArray<DataTableColumn<TRow>>>(() => {
    const list: DataTableColumn<TRow>[] = [];
    if (selectionEnabled) {
      list.push({
        id: SELECT_COLUMN_ID,
        enableSorting: false,
        enableHiding: false,
        meta: { width: 40, align: 'center' },
        header: ({ table }) => (
          <Checkbox
            size="sm"
            aria-label="Select all rows"
            checked={table.getIsAllRowsSelected()}
            indeterminate={table.getIsSomeRowsSelected() && !table.getIsAllRowsSelected()}
            onChange={table.getToggleAllRowsSelectedHandler()}
          />
        ),
        cell: ({ row }) => (
          <Checkbox
            size="sm"
            aria-label="Select row"
            checked={row.getIsSelected()}
            disabled={!row.getCanSelect()}
            onChange={row.getToggleSelectedHandler()}
            onClick={(e) => e.stopPropagation()}
          />
        ),
      });
    }
    list.push(...columns);
    if (rowActions) {
      list.push({
        id: ACTIONS_COLUMN_ID,
        enableSorting: false,
        enableHiding: false,
        meta: { width: 48, align: 'right' },
        header: () => <span className={styles.srOnly}>Actions</span>,
        cell: ({ row }) => (
          <Menu
            align="end"
            trigger={<IconButton size="sm" icon={<MoreHorizontal />} aria-label="Row actions" onClick={(e) => e.stopPropagation()} />}
          >
            {rowActions(row.original)}
          </Menu>
        ),
      });
    }
    return list;
  }, [columns, selectionEnabled, rowActions]);

  // ----- Sorting (controlled string) ---------------------------------------
  const sortKeyMaps = useMemo(() => {
    const keyToId: Record<string, string> = {};
    const idToKey: Record<string, string> = {};
    for (const c of columns) {
      const key = c.meta?.sortKey;
      const id = c.id ?? ('accessorKey' in c && typeof c.accessorKey === 'string' ? c.accessorKey : undefined);
      if (key && id) {
        keyToId[key] = id;
        idToKey[id] = key;
      }
    }
    return { keyToId, idToKey };
  }, [columns]);

  const sorting = useMemo(() => sortToSortingState(sort, sortKeyMaps.keyToId), [sort, sortKeyMaps]);
  const onSortingChange: OnChangeFn<SortingState> = (updater) => {
    const next = typeof updater === 'function' ? updater(sorting) : updater;
    onSortChange?.(sortingStateToSort(next, sortKeyMaps.idToKey));
  };

  // ----- Row selection ----------------------------------------------------
  const [innerSelection, setInnerSelection] = useState<RowSelectionState>({});
  const rowSelection = rowSelectionProp ?? innerSelection;
  const onRowSelectionChange: OnChangeFn<RowSelectionState> = (updater) => {
    const next = typeof updater === 'function' ? updater(rowSelection) : updater;
    if (!rowSelectionProp) setInnerSelection(next);
    onRowSelectionChangeProp?.(next);
  };
  const clearSelection = () => onRowSelectionChange({});

  // ----- Column visibility ------------------------------------------------
  const [innerVisibility, setInnerVisibility] = useState<ColumnVisibilityState>({});
  const columnVisibility = columnVisibilityProp ?? innerVisibility;
  const onColumnVisibilityChange: OnChangeFn<ColumnVisibilityState> = (updater) => {
    const next = typeof updater === 'function' ? updater(columnVisibility) : updater;
    if (!columnVisibilityProp) setInnerVisibility(next);
    onColumnVisibilityChangeProp?.(next);
  };

  // ----- Table ------------------------------------------------------------
  const rows = data ?? EMPTY;
  const table = useTable({
    features: dataTableFeatures,
    columns: allColumns,
    data: rows,
    getRowId,
    state: { sorting, rowSelection, columnVisibility },
    onSortingChange,
    onRowSelectionChange,
    onColumnVisibilityChange,
    manualSorting: true,
    enableSorting: !!onSortChange,
    enableMultiSort: false,
    enableSortingRemoval: true,
    enableRowSelection: selectionEnabled ? (typeof enableRowSelection === 'function' ? (row) => enableRowSelection(row.original) : true) : false,
    // Server-side tables: columns opt in to sorting explicitly.
    defaultColumn: { enableSorting: false },
  });

  const selectedIds = Object.keys(rowSelection);
  const selectedCount = selectedIds.length;
  const selectedRows = useMemo(() => (selectedCount ? table.getSelectedRowModel().rows.map((r) => r.original) : []), [table, selectedCount, rowSelection, rows]); // eslint-disable-line react-hooks/exhaustive-deps

  // ----- Horizontal scroll shadows (imperative; no state) -------------------
  const areaRef = useRef<HTMLDivElement>(null);
  const scrollerRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const area = areaRef.current;
    const el = scrollerRef.current;
    if (!area || !el) return;
    const update = () => {
      area.dataset.shadowLeft = el.scrollLeft > 1 ? 'true' : 'false';
      area.dataset.shadowRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1 ? 'true' : 'false';
    };
    update();
    el.addEventListener('scroll', update, { passive: true });
    const ro = new ResizeObserver(update);
    ro.observe(el);
    return () => {
      el.removeEventListener('scroll', update);
      ro.disconnect();
    };
  }, [rows, allColumns, columnVisibility]);

  // ----- Render helpers ---------------------------------------------------
  const visibleColumnCount = table.getVisibleLeafColumns().length || 1;
  const hideableColumns = table.getAllLeafColumns().filter((c) => c.getCanHide());
  const columnLabel = (c: (typeof hideableColumns)[number]) =>
    c.columnDef.meta?.label ?? (typeof c.columnDef.header === 'string' ? c.columnDef.header : c.id);

  const clickable = !!onRowClick;
  const initialLoading = isLoading && rows.length === 0 && !error;
  const refreshing = isLoading && rows.length > 0;
  const showToolbar = !!toolbar || enableColumnVisibility || (selectionEnabled && !!bulkActions);

  const alignClass = (meta: DataTableColumnMeta | undefined) =>
    cn(meta?.numeric && styles.numeric, meta?.align === 'right' && styles.alignRight, meta?.align === 'center' && styles.alignCenter, meta?.truncate && styles.truncate);

  return (
    <div className={cn(styles.root, dense && styles.dense, className)}>
      {showToolbar && (
        <div className={styles.toolbar}>
          {selectedCount > 0 && bulkActions ? (
            <div className={styles.bulkBar} role="region" aria-live="polite" aria-label="Bulk actions">
              <span className={styles.bulkCount}>
                <strong>{selectedCount}</strong> selected
              </span>
              <Button variant="link" size="sm" onClick={clearSelection}>
                Clear
              </Button>
              <div className={styles.bulkActions}>{bulkActions(selectedRows, { selectedIds, clear: clearSelection })}</div>
            </div>
          ) : (
            <div className={styles.toolbarMain}>{toolbar}</div>
          )}
          {enableColumnVisibility && hideableColumns.length > 0 && (
            <Menu
              align="end"
              closeOnSelect={false}
              aria-label="Toggle columns"
              trigger={
                <Button variant="outline" size="sm" leftIcon={<Columns3 />} className={styles.columnsButton}>
                  Columns
                </Button>
              }
            >
              <MenuLabel>Show columns</MenuLabel>
              {hideableColumns.map((c) => (
                <MenuItem key={c.id} checked={c.getIsVisible()} onSelect={() => c.toggleVisibility()}>
                  {columnLabel(c)}
                </MenuItem>
              ))}
            </Menu>
          )}
        </div>
      )}

      {initialLoading ? (
        <SkeletonTable rows={skeletonRows} cols={Math.min(visibleColumnCount, 8)} dense={dense} />
      ) : error && rows.length === 0 ? (
        <ErrorState error={error} onRetry={onRetry} size="sm" />
      ) : (
        <div ref={areaRef} className={styles.scrollArea} data-shadow-left="false" data-shadow-right="false">
          <div
            ref={scrollerRef}
            className={cn(styles.scroller, stickyHeader && styles.stickyScroller)}
            style={stickyHeader ? { maxHeight: maxHeight ?? '70vh' } : undefined}
            aria-busy={refreshing || undefined}
          >
            <table className={cn(styles.table, stickyHeader && styles.stickyHeader)} aria-label={ariaLabel}>
              <thead className={styles.thead}>
                {table.getHeaderGroups().map((group) => (
                  <tr key={group.id}>
                    {group.headers.map((header) => {
                      const meta = header.column.columnDef.meta;
                      const canSort = header.column.getCanSort();
                      const sorted = header.column.getIsSorted();
                      const content = header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext()) as ReactNode;
                      return (
                        <th
                          key={header.id}
                          scope="col"
                          colSpan={header.colSpan}
                          className={cn(styles.th, alignClass(meta), canSort && styles.sortable, meta?.headerClassName)}
                          style={metaStyle(meta)}
                          aria-sort={sorted ? (sorted === 'asc' ? 'ascending' : 'descending') : canSort ? 'none' : undefined}
                        >
                          {canSort ? (
                            <button type="button" className={styles.sortButton} onClick={header.column.getToggleSortingHandler()}>
                              <span className={styles.thLabel}>{content}</span>
                              <span className={cn(styles.sortIcon, sorted && styles.sortActive)} aria-hidden>
                                {sorted === 'asc' ? <ArrowUp /> : sorted === 'desc' ? <ArrowDown /> : <ChevronsUpDown />}
                              </span>
                            </button>
                          ) : (
                            <span className={styles.thLabel}>{content}</span>
                          )}
                        </th>
                      );
                    })}
                  </tr>
                ))}
              </thead>
              <tbody className={styles.tbody}>
                {table.getRowModel().rows.length === 0 ? (
                  <tr>
                    <td colSpan={visibleColumnCount} className={styles.stateCell}>
                      {emptyState ?? <EmptyState size="sm" title="Nothing here yet" description="No records match the current filters." />}
                    </td>
                  </tr>
                ) : (
                  table.getRowModel().rows.map((row) => {
                    const selected = selectionEnabled && row.getIsSelected();
                    return (
                      <tr
                        key={row.id}
                        className={cn(styles.tr, clickable && styles.clickable, selected && styles.selected, rowClassName?.(row.original))}
                        tabIndex={clickable ? 0 : undefined}
                        aria-selected={selectionEnabled ? row.getIsSelected() : undefined}
                        onClick={clickable ? (e) => onRowClick(row.original, e) : undefined}
                        onKeyDown={
                          clickable
                            ? (e) => {
                                if ((e.key === 'Enter' || e.key === ' ') && e.target === e.currentTarget) {
                                  e.preventDefault();
                                  onRowClick(row.original, e);
                                }
                              }
                            : undefined
                        }
                      >
                        {row.getVisibleCells().map((cell) => {
                          const meta = cell.column.columnDef.meta;
                          return (
                            <td key={cell.id} className={cn(styles.td, alignClass(meta), meta?.className)} style={metaStyle(meta)}>
                              {flexRender(cell.column.columnDef.cell, cell.getContext()) as ReactNode}
                            </td>
                          );
                        })}
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
          {refreshing && (
            <div className={styles.refreshing}>
              <Spinner size="sm" label="Refreshing" />
            </div>
          )}
        </div>
      )}

      {pagination && (
        <div className={styles.footer}>
          <Pagination {...pagination} disabled={pagination.disabled ?? isLoading} />
        </div>
      )}
    </div>
  );
}
