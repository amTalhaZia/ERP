import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MotionConfig } from 'framer-motion';
import { ConfirmDialog, type ConfirmDialogProps } from '@/components/ui/ConfirmDialog';

function renderDialog(props: Partial<ConfirmDialogProps> = {}) {
  const onClose = vi.fn();
  const onConfirm = vi.fn();
  render(
    <MotionConfig reducedMotion="always">
      <ConfirmDialog open title="Delete company?" destructive typeToConfirm="Acme Ltd" confirmLabel="Delete" onClose={onClose} onConfirm={onConfirm} {...props} />
    </MotionConfig>,
  );
  return { onClose, onConfirm };
}

describe('ConfirmDialog', () => {
  it('keeps confirm disabled until the exact text is typed', async () => {
    const user = userEvent.setup();
    const { onConfirm } = renderDialog();
    expect(screen.getByRole('alertdialog', { name: 'Delete company?' })).toBeInTheDocument();
    const confirm = screen.getByRole('button', { name: 'Delete' });
    expect(confirm).toBeDisabled();

    const input = screen.getByLabelText(/type acme ltd to confirm/i);
    await user.type(input, 'Acme');
    expect(confirm).toBeDisabled();
    await user.type(input, ' Ltd');
    expect(confirm).toBeEnabled();

    await user.click(confirm);
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('closes on Escape', async () => {
    const user = userEvent.setup();
    const { onClose } = renderDialog();
    await user.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('cancel calls onClose and not onConfirm', async () => {
    const user = userEvent.setup();
    const { onClose, onConfirm } = renderDialog({ typeToConfirm: undefined });
    await user.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onClose).toHaveBeenCalled();
    expect(onConfirm).not.toHaveBeenCalled();
  });
});
