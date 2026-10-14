'use client';

import { useRef, type ChangeEvent } from 'react';
import { Calendar } from 'lucide-react';
import { useMergedRefs } from '@/hooks/useMergedRefs';
import { Input, type InputProps } from './Input';
import styles from './DateInput.module.scss';

export interface DateInputProps extends Omit<InputProps, 'type' | 'value' | 'defaultValue' | 'onChange' | 'min' | 'max'> {
  /** ISO date `yyyy-mm-dd` or null/'' for empty. */
  value?: string | null;
  defaultValue?: string;
  /** Emits ISO `yyyy-mm-dd`, or null when cleared. */
  onValueChange?: (iso: string | null) => void;
  min?: string;
  max?: string;
  /** Native change event passthrough. */
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
}

/** Native date input with consistent styling and an explicit calendar button. */
export function DateInput({ value, defaultValue, onValueChange, onChange, min, max, disabled, readOnly, trailing, ref, ...rest }: DateInputProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const mergedRef = useMergedRefs(inputRef, ref);

  const openPicker = () => {
    const el = inputRef.current;
    if (!el || disabled || readOnly) return;
    try {
      // showPicker is not implemented in every browser (notably older Safari).
      if (typeof el.showPicker === 'function') el.showPicker();
      else el.focus();
    } catch {
      el.focus();
    }
  };

  return (
    <Input
      ref={mergedRef}
      type="date"
      value={value === null ? '' : value}
      defaultValue={defaultValue}
      min={min}
      max={max}
      disabled={disabled}
      readOnly={readOnly}
      inputClassName={styles.input}
      onChange={(event) => {
        onChange?.(event);
        onValueChange?.(event.target.value || null);
      }}
      trailing={
        <>
          {trailing}
          <button type="button" className={styles.picker} onClick={openPicker} aria-label="Open calendar" tabIndex={-1} disabled={disabled}>
            <Calendar />
          </button>
        </>
      }
      {...rest}
    />
  );
}
