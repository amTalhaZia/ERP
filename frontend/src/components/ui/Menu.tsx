'use client';

import Link from 'next/link';
import {
  cloneElement,
  createContext,
  isValidElement,
  useCallback,
  useContext,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type ComponentPropsWithoutRef,
  type HTMLAttributes,
  type KeyboardEvent,
  type MouseEvent,
  type ReactElement,
  type ReactNode,
  type Ref,
} from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Check } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useMergedRefs } from '@/hooks/useMergedRefs';
import { useClickOutside } from '@/hooks/useClickOutside';
import { useIsMounted } from '@/hooks/useMediaQuery';
import { MOTION } from './overlay';
import styles from './Menu.module.scss';

// ---------------------------------------------------------------------------
// Context
// ---------------------------------------------------------------------------

interface MenuContextValue {
  close: (returnFocus?: boolean) => void;
  closeOnSelect: boolean;
}

const MenuContext = createContext<MenuContextValue | null>(null);

function useMenuContext(): MenuContextValue {
  const ctx = useContext(MenuContext);
  if (!ctx) throw new Error('MenuItem must be rendered inside <Menu>');
  return ctx;
}

// ---------------------------------------------------------------------------
// Menu
// ---------------------------------------------------------------------------

type TriggerProps = HTMLAttributes<HTMLElement> & { ref?: Ref<HTMLElement>; disabled?: boolean };

export interface MenuProps {
  /** The element that opens the menu. Receives aria-haspopup / aria-expanded / onClick. */
  trigger: ReactElement<TriggerProps>;
  children: ReactNode;
  /** Horizontal alignment relative to the trigger. */
  align?: 'start' | 'end';
  /** Preferred vertical side; flips automatically when there is no room. */
  placement?: 'bottom' | 'top';
  /** Controlled open state. */
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  /** Close after an item is selected. Default true (checkbox items default to false). */
  closeOnSelect?: boolean;
  minWidth?: number;
  /** Make the menu at least as wide as the trigger. */
  matchTriggerWidth?: boolean;
  'aria-label'?: string;
  className?: string;
}

const ITEM_SELECTOR = '[role^="menuitem"]:not([aria-disabled="true"])';

export function Menu({
  trigger,
  children,
  align = 'start',
  placement = 'bottom',
  open: openProp,
  onOpenChange,
  closeOnSelect = true,
  minWidth = 180,
  matchTriggerWidth = false,
  'aria-label': ariaLabel,
  className,
}: MenuProps) {
  const mounted = useIsMounted();
  const reduced = useReducedMotion();
  const [innerOpen, setInnerOpen] = useState(false);
  const isControlled = openProp !== undefined;
  const open = isControlled ? openProp : innerOpen;
  const triggerRef = useRef<HTMLElement | null>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const menuId = useId();

  const setOpen = useCallback(
    (next: boolean) => {
      if (!isControlled) setInnerOpen(next);
      onOpenChange?.(next);
    },
    [isControlled, onOpenChange],
  );

  const close = useCallback(
    (returnFocus = true) => {
      setOpen(false);
      if (returnFocus) triggerRef.current?.focus({ preventScroll: true });
    },
    [setOpen],
  );

  useClickOutside([menuRef, triggerRef], () => setOpen(false), open);

  // Position + focus first item once the menu is in the DOM.
  useLayoutEffect(() => {
    if (!open) return;
    const menu = menuRef.current;
    const anchor = triggerRef.current;
    if (!menu || !anchor) return;

    const update = () => {
      const rect = anchor.getBoundingClientRect();
      const vw = window.innerWidth;
      const vh = window.innerHeight;
      const gap = 6;
      if (matchTriggerWidth) menu.style.minWidth = `${Math.max(minWidth, rect.width)}px`;
      const mw = menu.offsetWidth;
      const mh = menu.offsetHeight;

      let side = placement;
      if (side === 'bottom' && rect.bottom + gap + mh > vh - 8 && rect.top - gap - mh > 8) side = 'top';
      else if (side === 'top' && rect.top - gap - mh < 8) side = 'bottom';

      const top = side === 'bottom' ? rect.bottom + gap : rect.top - gap - mh;
      let left = align === 'start' ? rect.left : rect.right - mw;
      left = Math.max(8, Math.min(left, vw - mw - 8));

      menu.style.top = `${Math.max(8, top)}px`;
      menu.style.left = `${left}px`;
      menu.dataset.side = side;
    };
    update();

    const raf = requestAnimationFrame(() => {
      const first = menu.querySelector<HTMLElement>(ITEM_SELECTOR);
      (first ?? menu).focus({ preventScroll: true });
    });

    window.addEventListener('resize', update);
    window.addEventListener('scroll', update, true);
    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', update);
      window.removeEventListener('scroll', update, true);
    };
  }, [open, align, placement, minWidth, matchTriggerWidth]);

  const onMenuKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    const menu = menuRef.current;
    if (!menu) return;
    const items = Array.from(menu.querySelectorAll<HTMLElement>(ITEM_SELECTOR));
    const index = items.indexOf(document.activeElement as HTMLElement);

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        items[(index + 1) % items.length]?.focus();
        break;
      case 'ArrowUp':
        event.preventDefault();
        items[(index - 1 + items.length) % items.length]?.focus();
        break;
      case 'Home':
        event.preventDefault();
        items[0]?.focus();
        break;
      case 'End':
        event.preventDefault();
        items[items.length - 1]?.focus();
        break;
      case 'Escape':
        event.preventDefault();
        event.stopPropagation();
        close();
        break;
      case 'Tab':
        close(false);
        break;
      default:
        // First-letter typeahead.
        if (event.key.length === 1 && /\S/.test(event.key)) {
          const key = event.key.toLowerCase();
          const ordered = [...items.slice(index + 1), ...items.slice(0, index + 1)];
          ordered.find((el) => el.textContent?.trim().toLowerCase().startsWith(key))?.focus();
        }
    }
  };

  const triggerProps = isValidElement(trigger) ? trigger.props : undefined;
  const setTriggerRef = useMergedRefs<HTMLElement | null>(triggerRef, triggerProps?.ref);

  if (!isValidElement(trigger) || !triggerProps) return null;

  const triggerEl = cloneElement(trigger, {
    ref: setTriggerRef,
    'aria-haspopup': 'menu',
    'aria-expanded': open,
    'aria-controls': open ? menuId : undefined,
    onClick: (e: MouseEvent<HTMLElement>) => {
      triggerProps.onClick?.(e);
      if (e.defaultPrevented) return;
      setOpen(!open);
    },
    onKeyDown: (e: KeyboardEvent<HTMLElement>) => {
      triggerProps.onKeyDown?.(e);
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        setOpen(true);
      }
    },
  });

  return (
    <MenuContext.Provider value={{ close, closeOnSelect }}>
      {triggerEl}
      {mounted &&
        createPortal(
          <AnimatePresence>
            {open && (
              <motion.div
                ref={menuRef}
                id={menuId}
                role="menu"
                aria-label={ariaLabel}
                tabIndex={-1}
                className={cn(styles.menu, className)}
                style={{ minWidth }}
                onKeyDown={onMenuKeyDown}
                initial={reduced ? { opacity: 0 } : { opacity: 0, scale: 0.97, y: placement === 'bottom' ? -4 : 4 }}
                animate={{ opacity: 1, scale: 1, y: 0 }}
                exit={{ opacity: 0, transition: { duration: 0.1 } }}
                transition={{ duration: 0.14, ease: MOTION.ease }}
              >
                {children}
              </motion.div>
            )}
          </AnimatePresence>,
          document.body,
        )}
    </MenuContext.Provider>
  );
}

// ---------------------------------------------------------------------------
// Items
// ---------------------------------------------------------------------------

export interface MenuItemProps extends Omit<ComponentPropsWithoutRef<'button'>, 'onSelect' | 'onClick' | 'type'> {
  icon?: ReactNode;
  /** Right-aligned hint, e.g. "⌘D". */
  shortcut?: ReactNode;
  /** Secondary line under the label. */
  description?: ReactNode;
  destructive?: boolean;
  disabled?: boolean;
  onSelect?: (event: MouseEvent<HTMLElement>) => void;
  /** Renders a Next.js <Link> instead of a button. */
  href?: string;
  /** When defined, renders a `menuitemcheckbox` with a check mark. */
  checked?: boolean;
  /** Override the menu-level closeOnSelect for this item. */
  closeOnSelect?: boolean;
}

export function MenuItem({
  icon,
  shortcut,
  description,
  destructive,
  disabled,
  onSelect,
  href,
  checked,
  closeOnSelect,
  className,
  children,
  ...rest
}: MenuItemProps) {
  const ctx = useMenuContext();
  const isCheckbox = checked !== undefined;
  const shouldClose = closeOnSelect ?? (isCheckbox ? false : ctx.closeOnSelect);

  const handleClick = (event: MouseEvent<HTMLElement>) => {
    if (disabled) {
      event.preventDefault();
      return;
    }
    onSelect?.(event);
    if (shouldClose) ctx.close();
  };

  const classes = cn(styles.item, destructive && styles.destructive, disabled && styles.disabled, className);
  const body = (
    <>
      {isCheckbox ? (
        <span className={cn(styles.icon, styles.checkIcon)} aria-hidden>
          {checked && <Check />}
        </span>
      ) : (
        icon && (
          <span className={styles.icon} aria-hidden>
            {icon}
          </span>
        )
      )}
      <span className={styles.text}>
        <span className={styles.label}>{children}</span>
        {description && <span className={styles.description}>{description}</span>}
      </span>
      {shortcut && <span className={styles.shortcut}>{shortcut}</span>}
    </>
  );

  if (href && !disabled) {
    return (
      <Link href={href} role="menuitem" tabIndex={-1} className={classes} onClick={handleClick}>
        {body}
      </Link>
    );
  }

  return (
    <button
      type="button"
      role={isCheckbox ? 'menuitemcheckbox' : 'menuitem'}
      aria-checked={isCheckbox ? checked : undefined}
      aria-disabled={disabled || undefined}
      tabIndex={-1}
      className={classes}
      onClick={handleClick}
      {...rest}
    >
      {body}
    </button>
  );
}

export function MenuSeparator({ className, ...rest }: ComponentPropsWithoutRef<'div'>) {
  return <div role="separator" className={cn(styles.separator, className)} {...rest} />;
}

export function MenuLabel({ className, ...rest }: ComponentPropsWithoutRef<'div'>) {
  return <div role="presentation" className={cn(styles.groupLabel, className)} {...rest} />;
}

/** Group with an optional label — purely presentational. */
export function MenuGroup({ label, children, className }: { label?: ReactNode; children: ReactNode; className?: string }) {
  return (
    <div role="group" className={cn(styles.group, className)}>
      {label && <MenuLabel>{label}</MenuLabel>}
      {children}
    </div>
  );
}
