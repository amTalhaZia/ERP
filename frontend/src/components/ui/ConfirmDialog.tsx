'use client';

import { useId, useRef, useState, type ReactNode } from 'react';
import { TriangleAlert } from 'lucide-react';
import { cn } from '@/lib/cn';
import { Button } from './Button';
import { Input } from './Input';
import { Modal, type ModalProps } from './Modal';
import styles from './ConfirmDialog.module.scss';

export interface ConfirmDialogProps extends Pick<ModalProps, 'open' | 'onClose' | 'closeOnOverlayClick' | 'closeOnEscape'> {
  title: ReactNode;
  description?: ReactNode;
  /** Return a promise to get automatic loading state; the dialog stays open on rejection. */
  onConfirm: () => void | Promise<void>;
  confirmLabel?: string;
  cancelLabel?: string;
  /** Danger styling + warning icon. */
  destructive?: boolean;
  /** Require the user to type this exact string (e.g. the record name) before confirming. */
  typeToConfirm?: string;
  /** Controlled loading state (otherwise derived from `onConfirm`'s promise). */
  loading?: boolean;
  icon?: ReactNode;
  /** Extra content under the description (e.g. a summary list). */
  children?: ReactNode;
}

export function ConfirmDialog({
  open,
  onClose,
  title,
  description,
  onConfirm,
  confirmLabel = 'Confirm',
  cancelLabel = 'Cancel',
  destructive = false,
  typeToConfirm,
  loading,
  icon,
  children,
  closeOnOverlayClick,
  closeOnEscape,
}: ConfirmDialogProps) {
  const [pending, setPending] = useState(false);
  const [typed, setTyped] = useState('');
  const cancelRef = useRef<HTMLButtonElement>(null);
  const confirmRef = useRef<HTMLButtonElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const descId = useId();
  // With an icon the description sits beside it in the body (otherwise the body
  // would show a lone icon under a header that carries all the text).
  const showIcon = destructive || !!icon;

  const isLoading = loading ?? pending;
  const needsTyping = !!typeToConfirm;
  const canConfirm = !isLoading && (!needsTyping || typed.trim() === typeToConfirm);
  const initialFocusRef = needsTyping ? inputRef : destructive ? cancelRef : confirmRef;

  const handleClose = () => {
    if (isLoading) return;
    setTyped('');
    onClose();
  };

  const handleConfirm = async () => {
    if (!canConfirm) return;
    const result = onConfirm();
    if (result instanceof Promise) {
      setPending(true);
      try {
        await result;
        setTyped('');
      } catch {
        // Caller surfaces the error (toast / inline); keep the dialog open.
      } finally {
        setPending(false);
      }
    } else {
      setTyped('');
    }
  };

  return (
    <Modal
      open={open}
      onClose={handleClose}
      size="sm"
      role="alertdialog"
      title={title}
      description={showIcon ? undefined : description}
      aria-describedby={showIcon && description ? descId : undefined}
      showCloseButton={false}
      initialFocusRef={initialFocusRef}
      closeOnOverlayClick={closeOnOverlayClick ?? !isLoading}
      closeOnEscape={closeOnEscape ?? !isLoading}
      className={styles.dialog}
      footer={
        <>
          <Button ref={cancelRef} variant="outline" onClick={handleClose} disabled={isLoading}>
            {cancelLabel}
          </Button>
          <Button ref={confirmRef} variant={destructive ? 'danger' : 'primary'} onClick={handleConfirm} loading={isLoading} disabled={!canConfirm}>
            {confirmLabel}
          </Button>
        </>
      }
    >
      {showIcon && (
        <div className={styles.lead}>
          <div className={cn(styles.icon, destructive && styles.destructive)} aria-hidden>
            {icon ?? <TriangleAlert />}
          </div>
          {description && (
            <p id={descId} className={styles.description}>
              {description}
            </p>
          )}
        </div>
      )}
      {children}
      {needsTyping && (
        <form
          className={styles.typeToConfirm}
          onSubmit={(event) => {
            event.preventDefault();
            void handleConfirm();
          }}
        >
          <label htmlFor="confirm-type-input" className={styles.typeLabel}>
            Type <strong className={styles.typeTarget}>{typeToConfirm}</strong> to confirm
          </label>
          <Input
            ref={inputRef}
            id="confirm-type-input"
            value={typed}
            onChange={(e) => setTyped(e.target.value)}
            autoComplete="off"
            spellCheck={false}
            disabled={isLoading}
            size="md"
          />
        </form>
      )}
    </Modal>
  );
}
