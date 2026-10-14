import Link from 'next/link';
import { Fragment, type ComponentPropsWithoutRef, type ReactNode } from 'react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/cn';
import styles from './Breadcrumbs.module.scss';

export interface BreadcrumbItem {
  label: ReactNode;
  /** Omit on the current page (last item). */
  href?: string;
  icon?: ReactNode;
}

export interface BreadcrumbsProps extends Omit<ComponentPropsWithoutRef<'nav'>, 'children'> {
  items: BreadcrumbItem[];
}

/**
 * Breadcrumb trail. On phones, middle items collapse into an ellipsis and only
 * the first and last remain visible.
 */
export function Breadcrumbs({ items, className, ...rest }: BreadcrumbsProps) {
  const lastIndex = items.length - 1;
  const collapsible = items.length > 3;

  return (
    <nav aria-label="Breadcrumb" className={cn(styles.nav, className)} {...rest}>
      <ol className={styles.list}>
        {items.map((item, index) => {
          const isLast = index === lastIndex;
          const isMiddle = collapsible && index > 0 && index < lastIndex;
          const content = (
            <>
              {item.icon && <span className={styles.icon}>{item.icon}</span>}
              <span className={styles.label}>{item.label}</span>
            </>
          );
          return (
            <Fragment key={index}>
              {collapsible && index === 1 && (
                <li className={styles.ellipsisItem} aria-hidden>
                  <span className={styles.ellipsis}>…</span>
                  <ChevronRight className={styles.separator} />
                </li>
              )}
              <li className={cn(styles.item, isMiddle && styles.middle)}>
                {item.href && !isLast ? (
                  <Link href={item.href} className={styles.link}>
                    {content}
                  </Link>
                ) : (
                  <span className={cn(styles.current)} aria-current={isLast ? 'page' : undefined}>
                    {content}
                  </span>
                )}
                {!isLast && <ChevronRight className={styles.separator} aria-hidden />}
              </li>
            </Fragment>
          );
        })}
      </ol>
    </nav>
  );
}
