'use client';

import { useState, type ComponentPropsWithoutRef } from 'react';
import { cn } from '@/lib/cn';
import { initials as toInitials } from '@/lib/format';
import styles from './Avatar.module.scss';

export type AvatarSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl';
export type AvatarStatus = 'online' | 'offline' | 'busy' | 'away';

export interface AvatarProps extends Omit<ComponentPropsWithoutRef<'span'>, 'children'> {
  /** Full name — used for initials fallback and the accessible label. */
  name?: string | null;
  src?: string | null;
  alt?: string;
  size?: AvatarSize;
  shape?: 'circle' | 'square';
  /** Presence dot in the corner. */
  status?: AvatarStatus;
}

export function Avatar({ name, src, alt, size = 'md', shape = 'circle', status, className, ...rest }: AvatarProps) {
  const [failed, setFailed] = useState(false);
  const showImage = !!src && !failed;
  const label = alt ?? name ?? '';
  const text = toInitials(name);

  return (
    <span
      className={cn(styles.avatar, styles[size], styles[shape], className)}
      role="img"
      aria-label={label || undefined}
      aria-hidden={label ? undefined : true}
      {...rest}
    >
      {showImage ? (
        // Arbitrary remote avatar URLs — next/image would require remotePatterns per host.
        // eslint-disable-next-line @next/next/no-img-element
        <img src={src} alt="" className={styles.image} onError={() => setFailed(true)} draggable={false} />
      ) : (
        <span className={styles.initials} aria-hidden>
          {text || '?'}
        </span>
      )}
      {status && <span className={cn(styles.status, styles[`status-${status}`])} aria-hidden />}
    </span>
  );
}

export interface AvatarGroupProps extends ComponentPropsWithoutRef<'span'> {
  size?: AvatarSize;
  /** Children beyond this count collapse into a "+N" chip. */
  max?: number;
}

/** Overlapping stack of avatars. */
export function AvatarGroup({ size = 'sm', max = 4, className, children, ...rest }: AvatarGroupProps) {
  const items = Array.isArray(children) ? children.flat() : [children];
  const visible = items.slice(0, max);
  const overflow = items.length - visible.length;
  return (
    <span className={cn(styles.group, styles[`group-${size}`], className)} {...rest}>
      {visible}
      {overflow > 0 && (
        <span className={cn(styles.avatar, styles[size], styles.circle, styles.overflow)} aria-label={`${overflow} more`}>
          <span className={styles.initials}>+{overflow}</span>
        </span>
      )}
    </span>
  );
}
