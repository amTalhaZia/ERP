'use client';

import Link from 'next/link';
import {
  createContext,
  useContext,
  useId,
  useState,
  type ComponentPropsWithoutRef,
  type KeyboardEvent,
  type ReactNode,
} from 'react';
import { cn } from '@/lib/cn';
import styles from './Tabs.module.scss';

export type TabsVariant = 'underline' | 'pills';
export type TabsSize = 'sm' | 'md';

interface TabsContextValue {
  value: string | undefined;
  setValue: (value: string) => void;
  baseId: string;
  variant: TabsVariant;
  size: TabsSize;
}

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabs(component: string): TabsContextValue {
  const ctx = useContext(TabsContext);
  if (!ctx) throw new Error(`${component} must be rendered inside <Tabs>`);
  return ctx;
}

const slug = (v: string) => v.replace(/[^a-zA-Z0-9_-]/g, '_');

export interface TabsProps extends Omit<ComponentPropsWithoutRef<'div'>, 'defaultValue' | 'onChange'> {
  value?: string;
  defaultValue?: string;
  onValueChange?: (value: string) => void;
  variant?: TabsVariant;
  size?: TabsSize;
}

export function Tabs({ value, defaultValue, onValueChange, variant = 'underline', size = 'md', className, children, ...rest }: TabsProps) {
  const [inner, setInner] = useState(defaultValue);
  const isControlled = value !== undefined;
  const baseId = useId();

  const ctx: TabsContextValue = {
    value: isControlled ? value : inner,
    setValue: (next) => {
      if (!isControlled) setInner(next);
      onValueChange?.(next);
    },
    baseId,
    variant,
    size,
  };

  return (
    <TabsContext.Provider value={ctx}>
      <div className={cn(styles.tabs, className)} {...rest}>
        {children}
      </div>
    </TabsContext.Provider>
  );
}

export interface TabListProps extends ComponentPropsWithoutRef<'div'> {
  /** Stretch tabs to fill the width. */
  fullWidth?: boolean;
}

export function TabList({ fullWidth, className, children, onKeyDown, ...rest }: TabListProps) {
  const { variant, size } = useTabs('TabList');

  const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    onKeyDown?.(event);
    const list = event.currentTarget;
    const tabs = Array.from(list.querySelectorAll<HTMLElement>('[role="tab"]:not([aria-disabled="true"])'));
    const index = tabs.indexOf(document.activeElement as HTMLElement);
    if (index < 0) return;
    let next = -1;
    switch (event.key) {
      case 'ArrowRight':
        next = (index + 1) % tabs.length;
        break;
      case 'ArrowLeft':
        next = (index - 1 + tabs.length) % tabs.length;
        break;
      case 'Home':
        next = 0;
        break;
      case 'End':
        next = tabs.length - 1;
        break;
      default:
        return;
    }
    event.preventDefault();
    const target = tabs[next];
    target?.focus();
    target?.click(); // automatic activation
  };

  return (
    <div role="tablist" className={cn(styles.list, styles[variant], styles[size], fullWidth && styles.fullWidth, className)} onKeyDown={handleKeyDown} {...rest}>
      {children}
    </div>
  );
}

export interface TabProps extends Omit<ComponentPropsWithoutRef<'button'>, 'value' | 'type'> {
  value: string;
  icon?: ReactNode;
  /** Count bubble (e.g. number of records). */
  count?: number | string;
  disabled?: boolean;
  /** Route-driven tabs: render a Link. Pair with a controlled `value` from the pathname. */
  href?: string;
}

export function Tab({ value, icon, count, disabled, href, className, children, onClick, ...rest }: TabProps) {
  const { value: active, setValue, baseId } = useTabs('Tab');
  const selected = active === value;
  const id = `${baseId}-tab-${slug(value)}`;
  const panelId = `${baseId}-panel-${slug(value)}`;

  const content = (
    <>
      {icon && <span className={styles.icon}>{icon}</span>}
      <span className={styles.label}>{children}</span>
      {count !== undefined && <span className={styles.count}>{count}</span>}
    </>
  );

  const shared = {
    id,
    role: 'tab',
    'aria-selected': selected,
    'aria-controls': panelId,
    'aria-disabled': disabled || undefined,
    tabIndex: selected ? 0 : -1,
    className: cn(styles.tab, selected && styles.selected, disabled && styles.disabled, className),
  } as const;

  if (href && !disabled) {
    return (
      <Link href={href} {...shared} onClick={() => setValue(value)}>
        {content}
      </Link>
    );
  }

  return (
    <button
      type="button"
      disabled={disabled}
      {...shared}
      onClick={(event) => {
        onClick?.(event);
        if (!disabled) setValue(value);
      }}
      {...rest}
    >
      {content}
    </button>
  );
}

export interface TabPanelProps extends ComponentPropsWithoutRef<'div'> {
  value: string;
  /** Keep inactive panels mounted (hidden) to preserve their state. */
  keepMounted?: boolean;
}

export function TabPanel({ value, keepMounted = false, className, children, ...rest }: TabPanelProps) {
  const { value: active, baseId } = useTabs('TabPanel');
  const selected = active === value;
  if (!selected && !keepMounted) return null;
  return (
    <div
      role="tabpanel"
      id={`${baseId}-panel-${slug(value)}`}
      aria-labelledby={`${baseId}-tab-${slug(value)}`}
      hidden={!selected}
      tabIndex={0}
      className={cn(styles.panel, className)}
      {...rest}
    >
      {children}
    </div>
  );
}
