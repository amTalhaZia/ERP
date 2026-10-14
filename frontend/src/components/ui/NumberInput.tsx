'use client';

import { useState, type ChangeEvent, type FocusEvent, type KeyboardEvent } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { Input, type InputProps } from './Input';
import styles from './NumberInput.module.scss';

export interface NumberInputProps
  extends Omit<InputProps, 'type' | 'value' | 'defaultValue' | 'onChange' | 'min' | 'max' | 'step'> {
  value?: number | null;
  defaultValue?: number | null;
  /** `value` is null when the field is empty or not yet a valid number. `raw` is the typed text. */
  onValueChange?: (value: number | null, raw: string) => void;
  min?: number;
  max?: number;
  step?: number;
  /** Maximum decimal places accepted while typing. 0 = integers only. */
  precision?: number;
  allowNegative?: boolean;
  /** Show +/- stepper buttons. Default true. */
  stepper?: boolean;
  /** Native change event passthrough. */
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
}

function toText(v: number | null | undefined): string {
  return v === null || v === undefined || Number.isNaN(v) ? '' : String(v);
}

function parse(text: string): number | null {
  if (text === '' || text === '-' || text === '.' || text === '-.') return null;
  const n = Number(text);
  return Number.isFinite(n) ? n : null;
}

function sanitize(text: string, precision: number, allowNegative: boolean): string {
  let out = text.replace(/[^\d.-]/g, '');
  if (!allowNegative) out = out.replace(/-/g, '');
  else out = out.replace(/(?!^)-/g, '');
  if (precision === 0) out = out.replace(/\./g, '');
  else {
    const [head, ...tail] = out.split('.');
    if (tail.length > 0) out = `${head}.${tail.join('').slice(0, precision)}`;
  }
  return out;
}

function clamp(n: number, min?: number, max?: number): number {
  if (min !== undefined && n < min) return min;
  if (max !== undefined && n > max) return max;
  return n;
}

function roundTo(n: number, precision: number): number {
  const f = Math.pow(10, precision);
  return Math.round(n * f) / f;
}

export function NumberInput({
  value,
  defaultValue = null,
  onValueChange,
  min,
  max,
  step = 1,
  precision = 2,
  allowNegative = true,
  stepper = true,
  onChange,
  onBlur,
  onKeyDown,
  disabled,
  readOnly,
  trailing,
  ...rest
}: NumberInputProps) {
  const isControlled = value !== undefined;
  const [text, setText] = useState(() => toText(isControlled ? value : defaultValue));
  const [prevValue, setPrevValue] = useState(value);

  // Controlled value changed from outside (reset, async load) → resync the text.
  if (isControlled && value !== prevValue) {
    setPrevValue(value);
    if (parse(text) !== value) setText(toText(value));
  }

  const commit = (nextText: string) => {
    setText(nextText);
    onValueChange?.(parse(nextText), nextText);
  };

  const nudge = (direction: 1 | -1) => {
    if (disabled || readOnly) return;
    const current = parse(text) ?? (min !== undefined && min > 0 ? min : 0);
    const next = clamp(roundTo(current + direction * step, precision), min, max);
    commit(String(next));
  };

  const handleBlur = (event: FocusEvent<HTMLInputElement>) => {
    onBlur?.(event);
    const n = parse(text);
    if (n === null) {
      if (text !== '') commit('');
      return;
    }
    const clamped = clamp(n, min, max);
    if (clamped !== n || String(clamped) !== text) commit(String(clamped));
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    onKeyDown?.(event);
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      nudge(1);
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      nudge(-1);
    }
  };

  return (
    <Input
      type="text"
      inputMode={precision > 0 ? 'decimal' : 'numeric'}
      autoComplete="off"
      role="spinbutton"
      aria-valuemin={min}
      aria-valuemax={max}
      aria-valuenow={parse(text) ?? undefined}
      value={text}
      disabled={disabled}
      readOnly={readOnly}
      onChange={(event) => {
        onChange?.(event);
        commit(sanitize(event.target.value, precision, allowNegative));
      }}
      onBlur={handleBlur}
      onKeyDown={handleKeyDown}
      inputClassName={styles.input}
      trailing={
        <>
          {trailing}
          {stepper && !readOnly && (
            <span className={styles.stepper}>
              <button type="button" tabIndex={-1} aria-label="Increase" disabled={disabled} onClick={() => nudge(1)}>
                <ChevronUp />
              </button>
              <button type="button" tabIndex={-1} aria-label="Decrease" disabled={disabled} onClick={() => nudge(-1)}>
                <ChevronDown />
              </button>
            </span>
          )}
        </>
      }
      {...rest}
    />
  );
}
