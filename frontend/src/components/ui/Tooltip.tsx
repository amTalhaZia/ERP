'use client';

import {
  cloneElement,
  isValidElement,
  useCallback,
  useEffect,
  useId,
  useLayoutEffect,
  useRef,
  useState,
  type FocusEvent,
  type HTMLAttributes,
  type MouseEvent,
  type ReactElement,
  type ReactNode,
  type Ref,
} from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { cn } from '@/lib/cn';
import { useMergedRefs } from '@/hooks/useMergedRefs';
import { useIsMounted } from '@/hooks/useMediaQuery';
import styles from './Tooltip.module.scss';

export type TooltipPlacement = 'top' | 'bottom' | 'left' | 'right';

type TriggerProps = HTMLAttributes<HTMLElement> & { ref?: Ref<HTMLElement> };

export interface TooltipProps {
  content: ReactNode;
  children: ReactElement<TriggerProps>;
  placement?: TooltipPlacement;
  /** Show delay in ms. */
  delay?: number;
  /** Distance from the trigger in px. */
  offset?: number;
  disabled?: boolean;
  className?: string;
}

const GAP = 8;

export function position(trigger: DOMRect, tip: { width: number; height: number }, placement: TooltipPlacement, offset: number) {
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  let p = placement;

  // Flip when there is no room on the preferred side.
  if (p === 'top' && trigger.top - tip.height - offset < GAP) p = 'bottom';
  else if (p === 'bottom' && trigger.bottom + tip.height + offset > vh - GAP) p = 'top';
  else if (p === 'left' && trigger.left - tip.width - offset < GAP) p = 'right';
  else if (p === 'right' && trigger.right + tip.width + offset > vw - GAP) p = 'left';

  let top: number;
  let left: number;
  switch (p) {
    case 'top':
      top = trigger.top - tip.height - offset;
      left = trigger.left + trigger.width / 2 - tip.width / 2;
      break;
    case 'bottom':
      top = trigger.bottom + offset;
      left = trigger.left + trigger.width / 2 - tip.width / 2;
      break;
    case 'left':
      top = trigger.top + trigger.height / 2 - tip.height / 2;
      left = trigger.left - tip.width - offset;
      break;
    default:
      top = trigger.top + trigger.height / 2 - tip.height / 2;
      left = trigger.right + offset;
  }
  left = Math.max(GAP, Math.min(left, vw - tip.width - GAP));
  top = Math.max(GAP, Math.min(top, vh - tip.height - GAP));
  return { top, left, placement: p };
}

/** Open state with a show delay; the pending timer is cleared on hide/unmount. */
function useDelayedOpen(delay: number, disabled: boolean) {
  const [open, setOpen] = useState(false);
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const show = useCallback(() => {
    if (disabled) return;
    if (timer.current) clearTimeout(timer.current);
    timer.current = setTimeout(() => setOpen(true), delay);
  }, [delay, disabled]);

  const hide = useCallback(() => {
    if (timer.current) clearTimeout(timer.current);
    timer.current = null;
    setOpen(false);
  }, []);

  useEffect(
    () => () => {
      if (timer.current) clearTimeout(timer.current);
    },
    [],
  );

  return { open, show, hide };
}

export function Tooltip({ content, children, placement = 'top', delay = 300, offset = 6, disabled = false, className }: TooltipProps) {
  const mounted = useIsMounted();
  const reduced = useReducedMotion();
  const { open, show, hide } = useDelayedOpen(delay, disabled);
  const triggerRef = useRef<HTMLElement | null>(null);
  const tipRef = useRef<HTMLDivElement>(null);
  const id = useId();

  // Escape hides the tooltip (WCAG 1.4.13 dismissable).
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') hide();
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, hide]);

  // Position imperatively so we never set state from a layout effect.
  useLayoutEffect(() => {
    if (!open) return;
    const trigger = triggerRef.current;
    const tip = tipRef.current;
    if (!trigger || !tip) return;
    const update = () => {
      const rect = trigger.getBoundingClientRect();
      const { top, left, placement: p } = position(rect, { width: tip.offsetWidth, height: tip.offsetHeight }, placement, offset);
      tip.style.top = `${top}px`;
      tip.style.left = `${left}px`;
      tip.dataset.placement = p; // drives the arrow position in CSS
    };
    update();
    window.addEventListener('scroll', update, true);
    window.addEventListener('resize', update);
    return () => {
      window.removeEventListener('scroll', update, true);
      window.removeEventListener('resize', update);
    };
  }, [open, placement, offset]);

  const childProps = isValidElement(children) ? children.props : undefined;
  const setTriggerRef = useMergedRefs<HTMLElement | null>(triggerRef, childProps?.ref);

  if (!isValidElement(children) || !childProps) return children;

  const trigger = cloneElement(children, {
    ref: setTriggerRef,
    'aria-describedby': open ? [childProps['aria-describedby'], id].filter(Boolean).join(' ') : childProps['aria-describedby'],
    onMouseEnter: (e: MouseEvent<HTMLElement>) => {
      childProps.onMouseEnter?.(e);
      show();
    },
    onMouseLeave: (e: MouseEvent<HTMLElement>) => {
      childProps.onMouseLeave?.(e);
      hide();
    },
    onFocus: (e: FocusEvent<HTMLElement>) => {
      childProps.onFocus?.(e);
      show();
    },
    onBlur: (e: FocusEvent<HTMLElement>) => {
      childProps.onBlur?.(e);
      hide();
    },
  });

  const axis = placement === 'top' || placement === 'bottom' ? 'y' : 'x';
  const sign = placement === 'top' || placement === 'left' ? 4 : -4;

  return (
    <>
      {trigger}
      {mounted &&
        createPortal(
          <AnimatePresence>
            {open && content && (
              <motion.div
                ref={tipRef}
                id={id}
                role="tooltip"
                className={cn(styles.tooltip, className)}
                data-placement={placement}
                initial={reduced ? { opacity: 0 } : { opacity: 0, [axis]: sign }}
                animate={{ opacity: 1, x: 0, y: 0 }}
                exit={{ opacity: 0 }}
                transition={{ duration: 0.12, ease: [0.2, 0, 0, 1] }}
              >
                {content}
                <span className={styles.arrow} aria-hidden />
              </motion.div>
            )}
          </AnimatePresence>,
          document.body,
        )}
    </>
  );
}
