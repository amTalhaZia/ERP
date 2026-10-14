'use client';

import type { ComponentPropsWithRef, ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/cn';
import { resolveInvalid, type ControlSize } from './Input';
import styles from './Select.module.scss';

export interface SelectOption {
  value: string;
  label: ReactNode;
  disabled?: boolean;
}

export interface SelectOptionGroup {
  label: string;
  options: SelectOption[];
}

export interface SelectProps extends Omit<ComponentPropsWithRef<'select'>, 'size'> {
  size?: ControlSize;
  /** Options list; alternatively pass <option> children. */
  options?: Array<SelectOption | SelectOptionGroup>;
  /** Rendered as a disabled empty option; selected when value is ''. */
  placeholder?: string;
  invalid?: boolean;
  leading?: ReactNode;
  /** Class for the native <select>; `className` styles the outer box. */
  selectClassName?: string;
  fullWidth?: boolean;
}

function isGroup(o: SelectOption | SelectOptionGroup): o is SelectOptionGroup {
  return 'options' in o;
}

/** Styled native <select>. Use `value=""` with `placeholder` for the empty state. */
export function Select({
  size = 'md',
  options,
  placeholder,
  invalid,
  leading,
  className,
  selectClassName,
  fullWidth = true,
  disabled,
  value,
  defaultValue,
  children,
  'aria-invalid': ariaInvalid,
  ...rest
}: SelectProps) {
  const isInvalid = resolveInvalid(invalid, ariaInvalid);
  const uncontrolledDefault = value === undefined && defaultValue === undefined && placeholder ? '' : defaultValue;

  return (
    <div
      className={cn(
        styles.wrapper,
        styles[size],
        isInvalid && styles.invalid,
        disabled && styles.disabled,
        !fullWidth && styles.auto,
        className,
      )}
    >
      {leading && <span className={cn(styles.adornment, styles.leading)}>{leading}</span>}
      <select
        className={cn(styles.select, selectClassName)}
        disabled={disabled}
        value={value}
        defaultValue={uncontrolledDefault}
        aria-invalid={isInvalid || undefined}
        {...rest}
      >
        {placeholder !== undefined && (
          <option value="" disabled={rest.required}>
            {placeholder}
          </option>
        )}
        {options?.map((o) =>
          isGroup(o) ? (
            <optgroup key={o.label} label={o.label}>
              {o.options.map((opt) => (
                <option key={opt.value} value={opt.value} disabled={opt.disabled}>
                  {opt.label}
                </option>
              ))}
            </optgroup>
          ) : (
            <option key={o.value} value={o.value} disabled={o.disabled}>
              {o.label}
            </option>
          ),
        )}
        {children}
      </select>
      <span className={cn(styles.adornment, styles.chevron)} aria-hidden>
        <ChevronDown />
      </span>
    </div>
  );
}
