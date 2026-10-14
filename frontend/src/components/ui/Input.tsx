'use client';

import type { AriaAttributes, ComponentPropsWithRef, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './Input.module.scss';

export type ControlSize = 'sm' | 'md' | 'lg';

/** Resolves `invalid` from an explicit prop or an `aria-invalid` attribute (set by FormField). */
export function resolveInvalid(invalid: boolean | undefined, ariaInvalid: AriaAttributes['aria-invalid']): boolean {
  if (invalid !== undefined) return invalid;
  return ariaInvalid === true || ariaInvalid === 'true';
}

export interface InputProps extends Omit<ComponentPropsWithRef<'input'>, 'size' | 'prefix'> {
  size?: ControlSize;
  /** Icon or short text rendered before the value. */
  leading?: ReactNode;
  /** Icon, text or button rendered after the value. */
  trailing?: ReactNode;
  /** Error styling. Also inferred from `aria-invalid`. */
  invalid?: boolean;
  /** Class for the native <input>; `className` styles the outer box. */
  inputClassName?: string;
  /** Width behaviour; defaults to filling the container. */
  fullWidth?: boolean;
}

export function Input({
  size = 'md',
  leading,
  trailing,
  invalid,
  className,
  inputClassName,
  fullWidth = true,
  disabled,
  readOnly,
  type = 'text',
  'aria-invalid': ariaInvalid,
  ...rest
}: InputProps) {
  const isInvalid = resolveInvalid(invalid, ariaInvalid);
  return (
    <div
      className={cn(
        styles.wrapper,
        styles[size],
        isInvalid && styles.invalid,
        disabled && styles.disabled,
        readOnly && styles.readOnly,
        !fullWidth && styles.auto,
        className,
      )}
    >
      {leading !== undefined && leading !== null && <span className={cn(styles.adornment, styles.leading)}>{leading}</span>}
      <input
        type={type}
        className={cn(styles.input, inputClassName)}
        disabled={disabled}
        readOnly={readOnly}
        aria-invalid={isInvalid || undefined}
        {...rest}
      />
      {trailing !== undefined && trailing !== null && <span className={cn(styles.adornment, styles.trailing)}>{trailing}</span>}
    </div>
  );
}
