'use client';

import { useCallback } from 'react';
import { useAppSelector } from '@/store';
import { selectIsPlatformAdmin, selectPermissions } from '@/store/sessionSlice';
import { hasPermission, type Permission } from './permissions';

/**
 * `const can = useCan(); if (can('invoices.approve')) …`
 * Platform admins pass platform.* checks only; company permissions are never
 * implied by platform role (platform staff must switch into a company with an
 * explicit, audited membership).
 */
export function useCan() {
  const granted = useAppSelector(selectPermissions);
  const isPlatformAdmin = useAppSelector(selectIsPlatformAdmin);
  return useCallback(
    (required?: Permission | Permission[], mode: 'all' | 'any' = 'all') => {
      if (!required) return true;
      const list = Array.isArray(required) ? required : [required];
      if (isPlatformAdmin && list.every((p) => p.startsWith('platform.'))) return true;
      return hasPermission(granted, list, mode);
    },
    [granted, isPlatformAdmin],
  );
}
