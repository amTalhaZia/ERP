'use client';

import { useState, type ComponentPropsWithoutRef, type ReactNode } from 'react';
import { SlidersHorizontal } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useIsMobile } from '@/hooks/useMediaQuery';
import { Button } from './Button';
import { Drawer } from './Drawer';
import styles from './FilterBar.module.scss';

export interface FilterBarProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  /** Filter controls (Selects, DateInputs, SegmentedControls…). Give each a visible or sr-only label. */
  children: ReactNode;
  /** Number of filters currently applied — shown on the mobile button and enables "Clear". */
  activeCount?: number;
  onClear?: () => void;
  /** Always-visible controls (typically a SearchInput) rendered on the right / before the mobile button. */
  actions?: ReactNode;
  title?: string;
  clearLabel?: string;
  doneLabel?: string;
}

/**
 * Horizontal filter row on desktop; below 768px the filters collapse into a
 * "Filters" button that opens them in a bottom-sheet Drawer.
 */
export function FilterBar({
  children,
  activeCount = 0,
  onClear,
  actions,
  title = 'Filters',
  clearLabel = 'Clear filters',
  doneLabel = 'Done',
  className,
  ...rest
}: FilterBarProps) {
  const isMobile = useIsMobile();
  const [open, setOpen] = useState(false);

  if (isMobile) {
    return (
      <div className={cn(styles.bar, styles.mobile, className)} {...rest}>
        {actions && <div className={styles.actions}>{actions}</div>}
        <Button variant="outline" leftIcon={<SlidersHorizontal />} onClick={() => setOpen(true)} aria-haspopup="dialog" aria-expanded={open}>
          {title}
          {activeCount > 0 && <span className={styles.count}>{activeCount}</span>}
        </Button>
        <Drawer
          open={open}
          onClose={() => setOpen(false)}
          title={title}
          size="sm"
          footer={
            <>
              {onClear && (
                <Button variant="outline" onClick={onClear} disabled={activeCount === 0}>
                  {clearLabel}
                </Button>
              )}
              <Button onClick={() => setOpen(false)}>{doneLabel}</Button>
            </>
          }
        >
          <div className={styles.stack}>{children}</div>
        </Drawer>
      </div>
    );
  }

  return (
    <div className={cn(styles.bar, className)} role="group" aria-label={title} {...rest}>
      <div className={styles.filters}>
        {children}
        {onClear && activeCount > 0 && (
          <Button variant="link" size="sm" onClick={onClear} className={styles.clear}>
            {clearLabel}
          </Button>
        )}
      </div>
      {actions && <div className={styles.actions}>{actions}</div>}
    </div>
  );
}
