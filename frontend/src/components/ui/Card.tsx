import Link from 'next/link';
import type { ComponentPropsWithoutRef, ElementType, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './Card.module.scss';

export type CardPadding = 'none' | 'sm' | 'md' | 'lg';

export interface CardProps extends ComponentPropsWithoutRef<'div'> {
  /** Padding applied to the card itself. Use `none` when composing with CardHeader/CardBody/CardFooter. */
  padding?: CardPadding;
  /** Hover elevation + pointer; combine with `onClick` or `href`. */
  interactive?: boolean;
  /** Makes the whole card a Next.js link. */
  href?: string;
  /** Render element when not a link. */
  as?: ElementType;
}

export function Card({ padding = 'none', interactive, href, as: Tag = 'div', className, children, ...rest }: CardProps) {
  const classes = cn(styles.card, styles[`pad-${padding}`], (interactive || href) && styles.interactive, className);
  if (href) {
    return (
      <Link href={href} className={classes}>
        {children}
      </Link>
    );
  }
  return (
    <Tag className={classes} {...rest}>
      {children}
    </Tag>
  );
}

export interface CardHeaderProps extends Omit<ComponentPropsWithoutRef<'div'>, 'title'> {
  title?: ReactNode;
  description?: ReactNode;
  /** Right-aligned slot (buttons, menu). */
  actions?: ReactNode;
  /** Renders `title` as this heading level. */
  headingLevel?: 'h2' | 'h3' | 'h4';
  /** Remove the bottom border. */
  borderless?: boolean;
}

export function CardHeader({
  title,
  description,
  actions,
  headingLevel: Heading = 'h3',
  borderless,
  className,
  children,
  ...rest
}: CardHeaderProps) {
  return (
    <div className={cn(styles.header, borderless && styles.borderless, className)} {...rest}>
      <div className={styles.headerText}>
        {title && <Heading className={styles.title}>{title}</Heading>}
        {description && <p className={styles.description}>{description}</p>}
        {children}
      </div>
      {actions && <div className={styles.actions}>{actions}</div>}
    </div>
  );
}

export interface CardBodyProps extends ComponentPropsWithoutRef<'div'> {
  padding?: CardPadding;
}

export function CardBody({ padding = 'md', className, ...rest }: CardBodyProps) {
  return <div className={cn(styles.body, styles[`pad-${padding}`], className)} {...rest} />;
}

export interface CardFooterProps extends ComponentPropsWithoutRef<'div'> {
  align?: 'start' | 'end' | 'between';
}

export function CardFooter({ align = 'end', className, ...rest }: CardFooterProps) {
  return <div className={cn(styles.footer, styles[`align-${align}`], className)} {...rest} />;
}
