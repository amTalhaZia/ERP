'use client';

import { cloneElement, isValidElement, useId, type ComponentPropsWithoutRef, type ReactElement, type ReactNode } from 'react';
import { cn } from '@/lib/cn';
import styles from './FormField.module.scss';

/** Props FormField injects into its control (via cloneElement or the render-prop). */
export interface FieldControlProps {
  id: string;
  'aria-describedby'?: string;
  'aria-invalid'?: boolean;
  'aria-required'?: boolean;
  required?: boolean;
}

export type FieldSpan = 1 | 2 | 3 | 4 | 'full';

export interface FormFieldProps {
  label?: ReactNode;
  /** Keep the label for screen readers only (e.g. a search box in a toolbar). */
  hideLabel?: boolean;
  /** Id for the control. Generated when omitted. */
  id?: string;
  required?: boolean;
  /** Show "Optional" next to the label instead of an asterisk on required ones. */
  showOptional?: boolean;
  /** Helper text under the control. Hidden while `error` is shown unless `keepHint`. */
  hint?: ReactNode;
  keepHint?: boolean;
  /** Error message. Pass react-hook-form's `errors.field?.message`. */
  error?: ReactNode;
  /** Small right-aligned slot next to the label ("Forgot password?"). */
  labelAction?: ReactNode;
  /** Grid column span when inside FormGrid. */
  span?: FieldSpan;
  /** Label beside the control (settings-style rows) instead of above. */
  inline?: boolean;
  className?: string;
  children: ReactElement<Partial<FieldControlProps>> | ((props: FieldControlProps) => ReactNode);
}

export function FormField({
  label,
  hideLabel,
  id,
  required,
  showOptional,
  hint,
  keepHint,
  error,
  labelAction,
  span,
  inline,
  className,
  children,
}: FormFieldProps) {
  const generated = useId();
  const controlId = id ?? generated;
  const hintId = `${controlId}-hint`;
  const errorId = `${controlId}-error`;
  const hasError = !!error;
  const showHint = !!hint && (!hasError || keepHint);

  const describedBy = [showHint ? hintId : null, hasError ? errorId : null].filter(Boolean).join(' ') || undefined;

  const controlProps: FieldControlProps = {
    id: controlId,
    'aria-describedby': describedBy,
    'aria-invalid': hasError || undefined,
    'aria-required': required || undefined,
    required,
  };

  let control: ReactNode;
  if (typeof children === 'function') {
    control = children(controlProps);
  } else if (isValidElement(children)) {
    const existing = children.props['aria-describedby'];
    control = cloneElement(children, {
      ...controlProps,
      id: children.props.id ?? controlId,
      'aria-describedby': [existing, describedBy].filter(Boolean).join(' ') || undefined,
      required: children.props.required ?? required,
    });
  } else {
    control = children;
  }

  return (
    <div className={cn(styles.field, inline && styles.inline, span && styles[`span-${span}`], hasError && styles.hasError, className)}>
      {(label || labelAction) && (
        <div className={cn(styles.labelRow, hideLabel && !labelAction && styles.srOnly)}>
          {label && (
            <label htmlFor={controlId} className={cn(styles.label, hideLabel && styles.srOnly)}>
              {label}
              {required && !showOptional && (
                <span className={styles.required} aria-hidden>
                  {' '}
                  *
                </span>
              )}
              {!required && showOptional && <span className={styles.optional}> (optional)</span>}
            </label>
          )}
          {labelAction && <span className={styles.labelAction}>{labelAction}</span>}
        </div>
      )}
      <div className={styles.control}>
        {control}
        {showHint && (
          <p id={hintId} className={styles.hint}>
            {hint}
          </p>
        )}
        {hasError && (
          <p id={errorId} className={styles.error} role="alert">
            {error}
          </p>
        )}
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Layout helpers
// ---------------------------------------------------------------------------

export interface FormGridProps extends ComponentPropsWithoutRef<'div'> {
  /** Columns at ≥768px. Always 1 column below. */
  columns?: 1 | 2 | 3 | 4;
  gap?: 'sm' | 'md' | 'lg';
}

export function FormGrid({ columns = 2, gap = 'md', className, ...rest }: FormGridProps) {
  return <div className={cn(styles.grid, styles[`cols-${columns}`], styles[`gap-${gap}`], className)} {...rest} />;
}

export interface FormRowProps extends ComponentPropsWithoutRef<'div'> {
  gap?: 'sm' | 'md' | 'lg';
  /** Vertical alignment of children (useful when mixing inputs and buttons). */
  align?: 'start' | 'end' | 'center';
}

/** Children share a row equally and stack below 768px. */
export function FormRow({ gap = 'md', align = 'start', className, ...rest }: FormRowProps) {
  return <div className={cn(styles.row, styles[`gap-${gap}`], styles[`align-${align}`], className)} {...rest} />;
}

export interface FormSectionProps extends Omit<ComponentPropsWithoutRef<'section'>, 'title'> {
  title?: ReactNode;
  description?: ReactNode;
}

/** Titled group of fields with a divider — use to chunk long forms. */
export function FormSection({ title, description, className, children, ...rest }: FormSectionProps) {
  return (
    <section className={cn(styles.section, className)} {...rest}>
      {(title || description) && (
        <header className={styles.sectionHeader}>
          {title && <h3 className={styles.sectionTitle}>{title}</h3>}
          {description && <p className={styles.sectionDescription}>{description}</p>}
        </header>
      )}
      <div className={styles.sectionBody}>{children}</div>
    </section>
  );
}

export interface FormActionsProps extends ComponentPropsWithoutRef<'div'> {
  /** Stick to the bottom of the viewport while the form scrolls. Default true. */
  sticky?: boolean;
  align?: 'start' | 'end' | 'between';
  /** Left-side slot (e.g. "Delete" or a status note) when `align="between"`. */
  secondary?: ReactNode;
}

export function FormActions({ sticky = true, align = 'end', secondary, className, children, ...rest }: FormActionsProps) {
  return (
    <div className={cn(styles.actions, sticky && styles.sticky, styles[`actions-${align}`], className)} {...rest}>
      {secondary && <div className={styles.secondary}>{secondary}</div>}
      <div className={styles.primary}>{children}</div>
    </div>
  );
}
