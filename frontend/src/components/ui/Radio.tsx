'use client';

import {
  createContext,
  useContext,
  useId,
  useState,
  type ChangeEvent,
  type ComponentPropsWithRef,
  type ComponentPropsWithoutRef,
  type ReactNode,
} from 'react';
import { cn } from '@/lib/cn';
import { resolveInvalid } from './Input';
import styles from './Radio.module.scss';

interface RadioGroupContextValue {
  name: string;
  value: string | undefined;
  onChange: (value: string, event: ChangeEvent<HTMLInputElement>) => void;
  disabled?: boolean;
  size: 'sm' | 'md';
  invalid: boolean;
}

const RadioGroupContext = createContext<RadioGroupContextValue | null>(null);

export interface RadioGroupProps extends Omit<ComponentPropsWithoutRef<'div'>, 'onChange' | 'defaultValue'> {
  name?: string;
  value?: string;
  defaultValue?: string;
  onValueChange?: (value: string) => void;
  orientation?: 'vertical' | 'horizontal';
  disabled?: boolean;
  size?: 'sm' | 'md';
  invalid?: boolean;
  children: ReactNode;
}

export function RadioGroup({
  name,
  value,
  defaultValue,
  onValueChange,
  orientation = 'vertical',
  disabled,
  size = 'md',
  invalid,
  className,
  children,
  'aria-invalid': ariaInvalid,
  ...rest
}: RadioGroupProps) {
  const generatedName = useId();
  const [inner, setInner] = useState(defaultValue);
  const isControlled = value !== undefined;
  const current = isControlled ? value : inner;
  const isInvalid = resolveInvalid(invalid, ariaInvalid);

  const ctx: RadioGroupContextValue = {
    name: name ?? generatedName,
    value: current,
    onChange: (next) => {
      if (!isControlled) setInner(next);
      onValueChange?.(next);
    },
    disabled,
    size,
    invalid: isInvalid,
  };

  return (
    <RadioGroupContext.Provider value={ctx}>
      <div
        role="radiogroup"
        aria-invalid={isInvalid || undefined}
        aria-disabled={disabled || undefined}
        className={cn(styles.group, styles[orientation], className)}
        {...rest}
      >
        {children}
      </div>
    </RadioGroupContext.Provider>
  );
}

export interface RadioProps extends Omit<ComponentPropsWithRef<'input'>, 'type' | 'size'> {
  value: string;
  label?: ReactNode;
  description?: ReactNode;
  size?: 'sm' | 'md';
  invalid?: boolean;
  /** Class for the outer <label>. */
  className?: string;
}

/** Works inside <RadioGroup> (recommended) or standalone with your own name/checked. */
export function Radio({ value, label, description, size, invalid, className, disabled, checked, name, onChange, 'aria-invalid': ariaInvalid, ...rest }: RadioProps) {
  const group = useContext(RadioGroupContext);
  const resolvedSize = size ?? group?.size ?? 'md';
  const isDisabled = disabled ?? group?.disabled;
  const isInvalid = resolveInvalid(invalid, ariaInvalid) || !!group?.invalid;
  const isChecked = group ? group.value === value : checked;

  return (
    <label className={cn(styles.root, styles[resolvedSize], isDisabled && styles.disabled, isInvalid && styles.invalid, className)}>
      <span className={styles.control}>
        <input
          type="radio"
          className={styles.input}
          name={name ?? group?.name}
          value={value}
          checked={isChecked}
          disabled={isDisabled}
          onChange={(event) => {
            onChange?.(event);
            group?.onChange(value, event);
          }}
          {...rest}
        />
        <span className={styles.circle} aria-hidden>
          <span className={styles.dot} />
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
