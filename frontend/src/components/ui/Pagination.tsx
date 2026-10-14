'use client';

import { useId } from 'react';
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from 'lucide-react';
import { cn } from '@/lib/cn';
import { formatNumber } from '@/lib/format';
import type { Pagination as PaginationMeta } from '@/lib/api/types';
import { IconButton } from './IconButton';
import { Select } from './Select';
import styles from './Pagination.module.scss';

export interface PaginationProps {
  /** 1-based current page. */
  page: number;
  lastPage: number;
  total: number;
  perPage: number;
  onPageChange: (page: number) => void;
  /** Omit to hide the per-page selector. */
  onPerPageChange?: (perPage: number) => void;
  perPageOptions?: readonly number[];
  /** "Showing 1–25 of 1,204 results". Default true. */
  showSummary?: boolean;
  /** Noun for the summary. Default "results". */
  itemLabel?: string;
  /** Page buttons shown either side of the current page (desktop). */
  siblingCount?: number;
  disabled?: boolean;
  className?: string;
}

export const DEFAULT_PER_PAGE_OPTIONS = [10, 25, 50, 100] as const;

/** Adapts the API `meta.pagination` object to Pagination props. */
export function paginationFromMeta(meta: PaginationMeta | undefined): Pick<PaginationProps, 'page' | 'lastPage' | 'total' | 'perPage'> {
  return {
    page: meta?.page ?? 1,
    lastPage: meta?.last_page ?? 1,
    total: meta?.total ?? 0,
    perPage: meta?.per_page ?? 25,
  };
}

const ELLIPSIS = 'ellipsis' as const;
type PageToken = number | typeof ELLIPSIS;

export function pageRange(page: number, lastPage: number, siblingCount = 1): PageToken[] {
  const totalSlots = siblingCount * 2 + 5; // first, last, current, 2 siblings, 2 ellipses
  if (lastPage <= totalSlots) return Array.from({ length: lastPage }, (_, i) => i + 1);

  const left = Math.max(page - siblingCount, 1);
  const right = Math.min(page + siblingCount, lastPage);
  const showLeftDots = left > 2;
  const showRightDots = right < lastPage - 1;

  if (!showLeftDots && showRightDots) {
    const count = 3 + 2 * siblingCount;
    return [...Array.from({ length: count }, (_, i) => i + 1), ELLIPSIS, lastPage];
  }
  if (showLeftDots && !showRightDots) {
    const count = 3 + 2 * siblingCount;
    return [1, ELLIPSIS, ...Array.from({ length: count }, (_, i) => lastPage - count + i + 1)];
  }
  return [1, ELLIPSIS, ...Array.from({ length: right - left + 1 }, (_, i) => left + i), ELLIPSIS, lastPage];
}

export function Pagination({
  page,
  lastPage,
  total,
  perPage,
  onPageChange,
  onPerPageChange,
  perPageOptions = DEFAULT_PER_PAGE_OPTIONS,
  showSummary = true,
  itemLabel = 'results',
  siblingCount = 1,
  disabled,
  className,
}: PaginationProps) {
  const selectId = useId();
  const safeLast = Math.max(1, lastPage);
  const current = Math.min(Math.max(1, page), safeLast);
  const from = total === 0 ? 0 : (current - 1) * perPage + 1;
  const to = Math.min(current * perPage, total);
  const go = (p: number) => {
    const next = Math.min(Math.max(1, p), safeLast);
    if (next !== current && !disabled) onPageChange(next);
  };

  return (
    <nav className={cn(styles.root, className)} aria-label="Pagination">
      <div className={styles.left}>
        {showSummary && (
          <p className={styles.summary} aria-live="polite">
            {total === 0 ? (
              `No ${itemLabel}`
            ) : (
              <>
                Showing <span className={styles.num}>{formatNumber(from)}</span>–<span className={styles.num}>{formatNumber(to)}</span> of{' '}
                <span className={styles.num}>{formatNumber(total)}</span> {itemLabel}
              </>
            )}
          </p>
        )}
        {onPerPageChange && (
          <div className={styles.perPage}>
            <label htmlFor={selectId} className={styles.perPageLabel}>
              Rows per page
            </label>
            <Select
              id={selectId}
              size="sm"
              fullWidth={false}
              value={String(perPage)}
              disabled={disabled}
              onChange={(e) => onPerPageChange(Number(e.target.value))}
              options={perPageOptions.map((n) => ({ value: String(n), label: String(n) }))}
            />
          </div>
        )}
      </div>

      <div className={styles.controls}>
        <IconButton variant="outline" size="sm" icon={<ChevronsLeft />} aria-label="First page" className={styles.edge} disabled={disabled || current <= 1} onClick={() => go(1)} />
        <IconButton variant="outline" size="sm" icon={<ChevronLeft />} aria-label="Previous page" disabled={disabled || current <= 1} onClick={() => go(current - 1)} />

        <ol className={styles.pages}>
          {pageRange(current, safeLast, siblingCount).map((token, i) =>
            token === ELLIPSIS ? (
              <li key={`e${i}`} className={styles.ellipsis} aria-hidden>
                …
              </li>
            ) : (
              <li key={token}>
                <button
                  type="button"
                  className={cn(styles.page, token === current && styles.current)}
                  aria-current={token === current ? 'page' : undefined}
                  aria-label={`Page ${token}`}
                  disabled={disabled}
                  onClick={() => go(token)}
                >
                  {token}
                </button>
              </li>
            ),
          )}
        </ol>

        <span className={styles.compactLabel} aria-hidden>
          <span className={styles.num}>{current}</span> / <span className={styles.num}>{safeLast}</span>
        </span>

        <IconButton variant="outline" size="sm" icon={<ChevronRight />} aria-label="Next page" disabled={disabled || current >= safeLast} onClick={() => go(current + 1)} />
        <IconButton variant="outline" size="sm" icon={<ChevronsRight />} aria-label="Last page" className={styles.edge} disabled={disabled || current >= safeLast} onClick={() => go(safeLast)} />
      </div>
    </nav>
  );
}
