import type { SessionPayload } from '@/features/auth/types';

type HomeSession = Pick<Partial<SessionPayload>, 'current_company'> & { user?: Pick<SessionPayload['user'], 'is_platform_admin'> | null };

/** True for platform staff who are not currently inside a company. */
export function isPlatformOnly(s: HomeSession | null | undefined): boolean {
  return !!s?.user?.is_platform_admin && !s.current_company;
}

/**
 * Default landing page after sign-in. Platform staff without a company context
 * have no dashboard data, so they land on the companies console instead.
 */
export function homePathFor(s: HomeSession | null | undefined): string {
  return isPlatformOnly(s) ? '/platform/companies' : '/dashboard';
}
