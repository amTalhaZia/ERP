import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { Inbox } from 'lucide-react';
import { cn } from '@/lib/cn';
import styles from './EmptyState.module.scss';

export interface EmptyStateProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  icon?: ReactNode;
  title: ReactNode;
  description?: ReactNode;
  /** Primary call to action (a <Button>). */
  action?: ReactNode;
  secondaryAction?: ReactNode;
  size?: 'sm' | 'md' | 'lg';
}

export function EmptyState({ icon, title, description, action, secondaryAction, size = 'md', className, ...rest }: EmptyStateProps) {
  return (
    <div className={cn(styles.root, styles[size], className)} {...rest}>
      <div className={styles.icon} aria-hidden>
        {icon ?? <Inbox />}
      </div>
      <h3 className={styles.title}>{title}</h3>
      {description && <p className={styles.description}>{description}</p>}
      {(action || secondaryAction) && (
        <div className={styles.actions}>
          {action}
          {secondaryAction}
        </div>
      )}
    </div>
  );
}
