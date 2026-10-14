'use client';

import { useId, useState, type ComponentPropsWithRef, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './Switch.module.scss';

export interface SwitchProps extends Omit<ComponentPropsWithRef<'button'>, 'onChange' | 'value' | 'type'> {
  checked?: boolean;
  defaultChecked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
  label?: ReactNode;
  description?: ReactNode;
  /** Where the label sits relative to the toggle. */
  labelPosition?: 'start' | 'end';
  size?: 'sm' | 'md';
  /** When set, a hidden <input> carries the value in native form posts. */
  name?: string;
  value?: string;
  invalid?: boolean;
}

export function Switch({
  checked,
  defaultChecked = false,
  onCheckedChange,
  label,
  description,
  labelPosition = 'end',
  size = 'md',
  name,
  value = 'on',
  invalid,
  disabled,
  className,
  id,
  onClick,
  ...rest
}: SwitchProps) {
  const [inner, setInner] = useState(defaultChecked);
  const isControlled = checked !== undefined;
  const isOn = isControlled ? checked : inner;
  const generatedId = useId();
  const switchId = id ?? generatedId;
  const labelId = `${switchId}-label`;
  const descId = `${switchId}-desc`;

  const toggle = () => {
    const next = !isOn;
    if (!isControlled) setInner(next);
    onCheckedChange?.(next);
  };

  const control = (
    <button
      type="button"
      role="switch"
      id={switchId}
      aria-checked={isOn}
      aria-labelledby={label ? labelId : undefined}
      aria-describedby={description ? descId : undefined}
      aria-invalid={invalid || undefined}
      disabled={disabled}
      className={cn(styles.track, styles[size], isOn && styles.on, invalid && styles.invalid)}
      onClick={(event) => {
        onClick?.(event);
        if (!event.defaultPrevented) toggle();
      }}
      {...rest}
    >
      <span className={styles.thumb} aria-hidden />
    </button>
  );

  return (
    <div className={cn(styles.root, styles[`label-${labelPosition}`], disabled && styles.disabled, className)}>
      {name && <input type="hidden" name={name} value={isOn ? value : ''} />}
      {control}
      {(label || description) && (
        <span className={styles.text}>
          {label && (
            <label id={labelId} htmlFor={switchId} className={styles.label}>
              {label}
            </label>
          )}
          {description && (
            <span id={descId} className={styles.description}>
              {description}
            </span>
          )}
        </span>
      )}
    </div>
  );
}
