'use client';

import type { ReactNode } from 'react';
import { Button, type ButtonProps } from './Button';

export type IconButtonProps = Omit<ButtonProps, 'iconOnly' | 'leftIcon' | 'rightIcon' | 'children' | 'aria-label'> & {
  icon: ReactNode;
  /** Required: icon-only controls need an accessible name. */
  'aria-label': string;
};

/** Square, icon-only button. Defaults to the `ghost` variant. */
export function IconButton({ icon, variant = 'ghost', ...rest }: IconButtonProps) {
  return (
    <Button iconOnly variant={variant} {...rest}>
      {icon}
    </Button>
  );
}
