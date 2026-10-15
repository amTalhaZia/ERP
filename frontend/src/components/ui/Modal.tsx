'use client';

import { useId, useRef, type ComponentPropsWithoutRef, type PointerEvent, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { X } from 'lucide-react';
import { cn } from '@/lib/cn';
import { useFocusTrap } from '@/hooks/useFocusTrap';
import { useLockBodyScroll } from '@/hooks/useLockBodyScroll';
import { useIsMounted } from '@/hooks/useMediaQuery';
import { IconButton } from './IconButton';
import { MOTION, useEscapeToClose } from './overlay';
import styles from './Modal.module.scss';

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full';

export interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: ReactNode;
  description?: ReactNode;
  size?: ModalSize;
  children?: ReactNode;
  /** Footer slot — typically <Button>s. */
  footer?: ReactNode;
  /** Close when clicking the backdrop. Default true. */
  closeOnOverlayClick?: boolean;
  /** Close on Escape. Default true. */
  closeOnEscape?: boolean;
  showCloseButton?: boolean;
  /** Element to focus on open; defaults to the first focusable element. */
  initialFocusRef?: RefObject<HTMLElement | null>;
  /** Remove body padding (for tables, maps, full-bleed content). */
  noPadding?: boolean;
  role?: 'dialog' | 'alertdialog';
  /** Required when there is no `title`. */
  'aria-label'?: string;
  /** Id of an element inside the body that describes the dialog (when not using `description`). */
  'aria-describedby'?: string;
  className?: string;
  bodyClassName?: string;
}

export function Modal({
  open,
  onClose,
  title,
  description,
  size = 'md',
  children,
  footer,
  closeOnOverlayClick = true,
  closeOnEscape = true,
  showCloseButton = true,
  initialFocusRef,
  noPadding,
  role = 'dialog',
  'aria-label': ariaLabel,
  'aria-describedby': ariaDescribedBy,
  className,
  bodyClassName,
}: ModalProps) {
  const mounted = useIsMounted();
  const reduced = useReducedMotion();
  const panelRef = useRef<HTMLDivElement>(null);
  const baseId = useId();
  const titleId = `${baseId}-title`;
  const descId = `${baseId}-desc`;

  useLockBodyScroll(open);
  useFocusTrap(panelRef, open, { initialFocusRef });
  useEscapeToClose(open, closeOnEscape, onClose);

  const onOverlayPointerDown = (event: PointerEvent<HTMLDivElement>) => {
    if (closeOnOverlayClick && event.target === event.currentTarget) onClose();
  };

  if (!mounted) return null;

  return createPortal(
    <AnimatePresence>
      {open && (
        <div className={styles.root}>
          <motion.div
            className={styles.overlay}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: MOTION.duration, ease: MOTION.ease }}
            onPointerDown={onOverlayPointerDown}
          />
          <motion.div
            ref={panelRef}
            role={role}
            aria-modal="true"
            aria-labelledby={title ? titleId : undefined}
            aria-label={!title ? ariaLabel : undefined}
            aria-describedby={description ? descId : ariaDescribedBy}
            tabIndex={-1}
            className={cn(styles.panel, styles[size], className)}
            initial={reduced ? { opacity: 0 } : { opacity: 0, scale: 0.97, y: 8 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={reduced ? { opacity: 0 } : { opacity: 0, scale: 0.98, y: 6 }}
            transition={{ duration: MOTION.duration, ease: MOTION.ease }}
          >
            {(title || showCloseButton) && (
              <div className={styles.header}>
                <div className={styles.headerText}>
                  {title && (
                    <h2 id={titleId} className={styles.title}>
                      {title}
                    </h2>
                  )}
                  {description && (
                    <p id={descId} className={styles.description}>
                      {description}
                    </p>
                  )}
                </div>
                {showCloseButton && <IconButton icon={<X />} aria-label="Close" size="sm" className={styles.close} onClick={onClose} />}
              </div>
            )}
            <div className={cn(styles.body, noPadding && styles.noPadding, bodyClassName)}>{children}</div>
            {footer && <div className={styles.footer}>{footer}</div>}
          </motion.div>
        </div>
      )}
    </AnimatePresence>,
    document.body,
  );
}

/** Convenience footer layout for custom modal footers. */
export function ModalFooterActions({ className, ...rest }: ComponentPropsWithoutRef<'div'>) {
  return <div className={cn(styles.footerActions, className)} {...rest} />;
}
