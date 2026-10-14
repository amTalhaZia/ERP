'use client';

import Link from 'next/link';
import type { ComponentPropsWithRef, ReactNode, Ref } from 'react';
import { cn } from '@/lib/cn';
import { Spinner } from './Spinner';
import styles from './Button.module.scss';

export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger' | 'link';
export type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonOwnProps {
  variant?: ButtonVariant;
  size?: ButtonSize;
  /** Shows a spinner, sets aria-busy and disables interaction. */
  loading?: boolean;
  /** Text announced while loading (visually hidden). */
  loadingLabel?: string;
  leftIcon?: ReactNode;
  rightIcon?: ReactNode;
  fullWidth?: boolean;
  /** Renders a Next.js <Link>. All button visual props still apply. */
  href?: string;
  /** Anchor-only attributes when `href` is set. */
  target?: string;
  rel?: string;
  /** Prefetch behaviour forwarded to <Link>. */
  prefetch?: boolean;
  children?: ReactNode;
}

type IconOnlyProps =
  | { iconOnly: true; 'aria-label': string }
  | { iconOnly?: false; 'aria-label'?: string };

export type ButtonProps = ButtonOwnProps &
  IconOnlyProps &
  Omit<ComponentPropsWithRef<'button'>, 'children' | 'aria-label'>;

export function Button({
  variant = 'primary',
  size = 'md',
  loading = false,
  loadingLabel = 'Loading',
  leftIcon,
  rightIcon,
  iconOnly = false,
  fullWidth = false,
  href,
  target,
  rel,
  prefetch,
  disabled,
  className,
  children,
  type,
  ref,
  onClick,
  ...rest
}: ButtonProps) {
  const isDisabled = disabled || loading;
  const classes = cn(
    styles.button,
    styles[variant],
    styles[size],
    iconOnly && styles.iconOnly,
    fullWidth && styles.fullWidth,
    loading && styles.loading,
    className,
  );

  const content = (
    <>
      {loading && (
        <span className={styles.spinnerWrap} aria-hidden>
          <Spinner size={size === 'lg' ? 'md' : 'sm'} label={null} />
        </span>
      )}
      {loading && <span className={styles.srOnly}>{loadingLabel}</span>}
      <span className={styles.content}>
        {leftIcon && <span className={styles.icon}>{leftIcon}</span>}
        {iconOnly ? <span className={styles.icon}>{children}</span> : children && <span className={styles.label}>{children}</span>}
        {rightIcon && <span className={styles.icon}>{rightIcon}</span>}
      </span>
    </>
  );

  if (href && !isDisabled) {
    // Anchor rendering: only forward the attributes that make sense on <a>.
    const { 'aria-label': ariaLabel, title, id, tabIndex, onFocus, onBlur, onMouseEnter, onMouseLeave, onKeyDown } = rest;
    return (
      <Link
        href={href}
        target={target}
        rel={rel ?? (target === '_blank' ? 'noopener noreferrer' : undefined)}
        prefetch={prefetch}
        className={classes}
        aria-label={ariaLabel}
        title={title}
        id={id}
        tabIndex={tabIndex}
        onFocus={onFocus as never}
        onBlur={onBlur as never}
        onMouseEnter={onMouseEnter as never}
        onMouseLeave={onMouseLeave as never}
        onKeyDown={onKeyDown as never}
        onClick={onClick as never}
        ref={ref as unknown as Ref<HTMLAnchorElement>}
      >
        {content}
      </Link>
    );
  }

  return (
    <button
      ref={ref}
      type={type ?? 'button'}
      className={classes}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      aria-disabled={isDisabled || undefined}
      onClick={onClick}
      {...rest}
    >
      {content}
    </button>
  );
}
