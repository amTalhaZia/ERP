'use client';

import { useRef, useState, type ChangeEvent, type KeyboardEvent } from 'react';
import { Search, X } from 'lucide-react';
import { useDebouncedCallback } from '@/hooks/useDebounce';
import { useMergedRefs } from '@/hooks/useMergedRefs';
import { Input, type InputProps } from './Input';
import styles from './SearchInput.module.scss';

export interface SearchInputProps extends Omit<InputProps, 'type' | 'value' | 'defaultValue' | 'onChange'> {
  value?: string;
  defaultValue?: string;
  /** Fires on every keystroke with the string value. */
  onValueChange?: (value: string) => void;
  /** Fires after `debounceMs` of inactivity — wire this to your list query's `search` param. */
  onDebouncedChange?: (value: string) => void;
  debounceMs?: number;
  /** Show the clear (×) button when there is text. Default true. */
  clearable?: boolean;
  onClear?: () => void;
  /** Native change event passthrough. */
  onChange?: (event: ChangeEvent<HTMLInputElement>) => void;
}

export function SearchInput({
  value,
  defaultValue = '',
  onValueChange,
  onDebouncedChange,
  debounceMs = 300,
  clearable = true,
  onClear,
  onChange,
  onKeyDown,
  placeholder = 'Search…',
  ref,
  ...rest
}: SearchInputProps) {
  const [inner, setInner] = useState(defaultValue);
  const isControlled = value !== undefined;
  const text = isControlled ? value : inner;
  const inputRef = useRef<HTMLInputElement>(null);
  const mergedRef = useMergedRefs(inputRef, ref);
  const debounced = useDebouncedCallback((next: string) => onDebouncedChange?.(next), debounceMs);

  const update = (next: string) => {
    if (!isControlled) setInner(next);
    onValueChange?.(next);
    debounced(next);
  };

  const clear = () => {
    update('');
    debounced.flush();
    onClear?.();
    inputRef.current?.focus();
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    onKeyDown?.(event);
    if (event.key === 'Escape' && text) {
      event.preventDefault();
      clear();
    } else if (event.key === 'Enter') {
      debounced.flush();
    }
  };

  return (
    <Input
      ref={mergedRef}
      type="search"
      role="searchbox"
      inputMode="search"
      autoComplete="off"
      enterKeyHint="search"
      placeholder={placeholder}
      value={text}
      onChange={(event) => {
        onChange?.(event);
        update(event.target.value);
      }}
      onKeyDown={handleKeyDown}
      leading={<Search aria-hidden />}
      trailing={
        clearable && text ? (
          <button type="button" className={styles.clear} onClick={clear} aria-label="Clear search" tabIndex={-1}>
            <X />
          </button>
        ) : undefined
      }
      {...rest}
    />
  );
}
