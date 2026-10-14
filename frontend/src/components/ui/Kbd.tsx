import type { ComponentPropsWithoutRef } from 'react';
import { cn } from '@/lib/cn';
import styles from './Kbd.module.scss';

export interface KbdProps extends ComponentPropsWithoutRef<'kbd'> {
  size?: 'sm' | 'md';
}

/** Keyboard key hint: <Kbd>⌘</Kbd><Kbd>K</Kbd> */
export function Kbd({ size = 'md', className, ...rest }: KbdProps) {
  return <kbd className={cn(styles.kbd, styles[size], className)} {...rest} />;
}
