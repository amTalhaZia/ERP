import Link from 'next/link';
import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import { cn } from '@/lib/cn';
import styles from './PageHeader.module.scss';

export interface PageHeaderProps extends Omit<ComponentPropsWithoutRef<'header'>, 'title'> {
  title: ReactNode;
  description?: ReactNode;
  /** Usually <Breadcrumbs items=…/>. */
  breadcrumbs?: ReactNode;
  /** Buttons; wrap onto their own row on phones. */
  actions?: ReactNode;
  /** Renders a back link above the title. */
  backHref?: string;
  backLabel?: string;
  /** Inline badges / metadata rendered next to the title. */
  meta?: ReactNode;
  /** Content below the header (tabs, filters). */
  children?: ReactNode;
}

export function PageHeader({ title, description, breadcrumbs, actions, backHref, backLabel = 'Back', meta, className, children, ...rest }: PageHeaderProps) {
  return (
    <header className={cn(styles.header, className)} {...rest}>
      {(breadcrumbs || backHref) && (
        <div className={styles.top}>
          {backHref && (
            <Link href={backHref} className={styles.back}>
              <ArrowLeft aria-hidden />
              <span>{backLabel}</span>
            </Link>
          )}
          {breadcrumbs}
        </div>
      )}
      <div className={styles.main}>
        <div className={styles.text}>
          <div className={styles.titleRow}>
            <h1 className={styles.title}>{title}</h1>
            {meta && <div className={styles.meta}>{meta}</div>}
          </div>
          {description && <p className={styles.description}>{description}</p>}
        </div>
        {actions && <div className={styles.actions}>{actions}</div>}
      </div>
      {children && <div className={styles.below}>{children}</div>}
    </header>
  );
}
