'use client';

import { useCallback, type ComponentPropsWithRef, type ReactNode } from 'react';
import { Check, Minus } from 'lucide-react';
import { cn } from '@/lib/cn';
import { assignRef } from '@/hooks/useMergedRefs';
import { resolveInvalid } from './Input';
import styles from './Checkbox.module.scss';

export interface CheckboxProps extends Omit<ComponentPropsWithRef<'input'>, 'type' | 'size'> {
  label?: ReactNode;
  description?: ReactNode;
  /** Mixed state (e.g. "some rows selected"). Visual + aria only; `checked` still drives the value. */
  indeterminate?: boolean;
  size?: 'sm' | 'md';
  invalid?: boolean;
  /** Class for the outer <label>. */
  className?: string;
}

export function Checkbox({
  label,
  description,
  indeterminate = false,
  size = 'md',
  invalid,
  className,
  disabled,
  ref,
  'aria-invalid': ariaInvalid,
  ...rest
}: CheckboxProps) {
  const isInvalid = resolveInvalid(invalid, ariaInvalid);

  const setRef = useCallback(
    (el: HTMLInputElement | null) => {
      if (el) el.indeterminate = indeterminate;
      assignRef(ref, el);
    },
    [indeterminate, ref],
  );

  return (
    <label className={cn(styles.root, styles[size], disabled && styles.disabled, isInvalid && styles.invalid, className)}>
      <span className={styles.control}>
        <input
          ref={setRef}
          type="checkbox"
          className={styles.input}
          disabled={disabled}
          aria-invalid={isInvalid || undefined}
          aria-checked={indeterminate ? 'mixed' : undefined}
          {...rest}
        />
        <span className={styles.box} aria-hidden>
          {indeterminate ? <Minus className={styles.icon} strokeWidth={3} /> : <Check className={styles.icon} strokeWidth={3} />}
        </span>
      </span>
      {(label || description) && (
        <span className={styles.text}>
          {label && <span className={styles.label}>{label}</span>}
          {description && <span className={styles.description}>{description}</span>}
        </span>
      )}
    </label>
  );
}
