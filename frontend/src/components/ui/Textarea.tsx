'use client';

import { useCallback, useEffect, useRef, type ComponentPropsWithRef } from 'react';
import { cn } from '@/lib/cn';
import { useMergedRefs } from '@/hooks/useMergedRefs';
import { resolveInvalid } from './Input';
import styles from './Textarea.module.scss';

export interface TextareaProps extends ComponentPropsWithRef<'textarea'> {
  invalid?: boolean;
  /** Grow with content between `minRows` and `maxRows`. */
  autoResize?: boolean;
  minRows?: number;
  maxRows?: number;
  /** Class for the native element; `className` styles the outer box. */
  textareaClassName?: string;
}

export function Textarea({
  invalid,
  autoResize = false,
  minRows = 3,
  maxRows = 12,
  className,
  textareaClassName,
  disabled,
  readOnly,
  rows,
  value,
  onInput,
  ref,
  'aria-invalid': ariaInvalid,
  ...rest
}: TextareaProps) {
  const isInvalid = resolveInvalid(invalid, ariaInvalid);
  const localRef = useRef<HTMLTextAreaElement>(null);
  const mergedRef = useMergedRefs(localRef, ref);

  const resize = useCallback(() => {
    const el = localRef.current;
    if (!el || !autoResize) return;
    const style = window.getComputedStyle(el);
    const lineHeight = parseFloat(style.lineHeight) || 20;
    const padding = parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
    const min = lineHeight * minRows + padding;
    const max = lineHeight * maxRows + padding;
    el.style.height = 'auto';
    const next = Math.min(max, Math.max(min, el.scrollHeight));
    el.style.height = `${next}px`;
    el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
  }, [autoResize, minRows, maxRows]);

  // Re-measure when the controlled value changes programmatically.
  useEffect(() => {
    resize();
  }, [resize, value]);

  const handleInput: TextareaProps['onInput'] = (event) => {
    onInput?.(event);
    resize();
  };

  return (
    <div className={cn(styles.wrapper, isInvalid && styles.invalid, disabled && styles.disabled, readOnly && styles.readOnly, className)}>
      <textarea
        ref={mergedRef}
        className={cn(styles.textarea, autoResize && styles.autoResize, textareaClassName)}
        rows={rows ?? minRows}
        disabled={disabled}
        readOnly={readOnly}
        value={value}
        onInput={handleInput}
        aria-invalid={isInvalid || undefined}
        {...rest}
      />
    </div>
  );
}
