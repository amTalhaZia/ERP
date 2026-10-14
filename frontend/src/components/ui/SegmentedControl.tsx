'use client';

import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './SegmentedControl.module.scss';

export interface SegmentedOption<T extends string = string> {
  value: T;
  label: ReactNode;
  icon?: ReactNode;
  disabled?: boolean;
  /** Icon-only option: label becomes the accessible name. */
  iconOnly?: boolean;
}

export interface SegmentedControlProps<T extends string = string> {
  options: ReadonlyArray<SegmentedOption<T>>;
  value: T;
  onChange: (value: T) => void;
  size?: 'sm' | 'md';
  fullWidth?: boolean;
  disabled?: boolean;
  'aria-label'?: string;
  'aria-labelledby'?: string;
  className?: string;
}

/** Exclusive choice between a few options (view switchers, period pickers). */
export function SegmentedControl<T extends string = string>({
  options,
  value,
  onChange,
  size = 'md',
  fullWidth,
  disabled,
  className,
  ...aria
}: SegmentedControlProps<T>) {
  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const enabled = options.filter((o) => !o.disabled);
    const index = enabled.findIndex((o) => o.value === value);
    let next = -1;
    switch (event.key) {
      case 'ArrowRight':
      case 'ArrowDown':
        next = (index + 1) % enabled.length;
        break;
      case 'ArrowLeft':
      case 'ArrowUp':
        next = (index - 1 + enabled.length) % enabled.length;
        break;
      case 'Home':
        next = 0;
        break;
      case 'End':
        next = enabled.length - 1;
        break;
      default:
        return;
    }
    event.preventDefault();
    const target = enabled[next];
    if (target) {
      onChange(target.value);
      event.currentTarget.querySelector<HTMLElement>(`[data-value="${target.value}"]`)?.focus();
    }
  };

  return (
    <div
      role="radiogroup"
      aria-label={aria['aria-label']}
      aria-labelledby={aria['aria-labelledby']}
      aria-disabled={disabled || undefined}
      className={cn(styles.root, styles[size], fullWidth && styles.fullWidth, disabled && styles.disabled, className)}
      onKeyDown={handleKeyDown}
    >
      {options.map((option) => {
        const selected = option.value === value;
        const isDisabled = disabled || option.disabled;
        return (
          <button
            key={option.value}
            type="button"
            role="radio"
            aria-checked={selected}
            aria-label={option.iconOnly && typeof option.label === 'string' ? option.label : undefined}
            data-value={option.value}
            tabIndex={selected ? 0 : -1}
            disabled={isDisabled}
            className={cn(styles.option, selected && styles.selected, option.iconOnly && styles.iconOnly)}
            onClick={() => !isDisabled && onChange(option.value)}
          >
            {option.icon && <span className={styles.icon}>{option.icon}</span>}
            {!option.iconOnly && <span className={styles.label}>{option.label}</span>}
          </button>
        );
      })}
    </div>
  );
}
