'use client';

import type { ReactNode } from 'react';
import { usePathname } from 'next/navigation';
import { Building2, Hexagon, LogOut } from 'lucide-react';
import { AccessDeniedState, Button } from '@/components/ui';
import { useAppSelector } from '@/store';
import { selectCompanies, selectIsPlatformAdmin, selectUser } from '@/store/sessionSlice';
import { useCan } from '@/lib/auth/useCan';
import { useSession } from '@/lib/auth/SessionProvider';
import { findNavTrail, type NavItem } from '@/lib/navigation';
import styles from './RouteGuard.module.scss';

/**
 * Routes reachable by more people than their nav entry's permission suggests:
 * the dashboard renders its own per-permission content, and the exports inbox
 * only lists the viewer's own exports.
 */
const UNGUARDED = ['/dashboard', '/reports/exports'];

/** The nav entry whose permission governs `pathname`, or null when the route is not permission-mapped. */
export function guardingNavItem(pathname: string): NavItem | null {
  if (UNGUARDED.some((p) => pathname === p || pathname.startsWith(p + '/'))) return null;
  const item = findNavTrail(pathname).at(-1);
  // A section header (e.g. /finance) is a prefix of pages with their own rules — only guard leaves.
  if (!item || item.children?.length) return null;
  return item;
}

/**
 * Client-side route guard (UX only — the API enforces every permission). Shows
 * an "Access denied" state instead of a page whose nav entry the user cannot see.
 */
export function RequirePermission({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const can = useCan();
  const isPlatformAdmin = useAppSelector(selectIsPlatformAdmin);
  const item = guardingNavItem(pathname);
  const allowed = !item || (item.platform ? isPlatformAdmin : can(item.permission, 'any'));
  if (!allowed) return <AccessDeniedState />;
  return <>{children}</>;
}

/** Full-page state for a signed-in user with no active company membership. */
export function NoCompanyAccess() {
  const user = useAppSelector(selectUser);
  const companies = useAppSelector(selectCompanies);
  const { logout, switchCompany } = useSession();

  return (
    <div className={styles.page}>
      <main className={styles.card} aria-labelledby="no-company-title">
        <div className={styles.logo}>
          <Hexagon size={20} strokeWidth={2.25} aria-hidden /> Enterprise ERP
        </div>
        <div className={styles.icon} aria-hidden>
          <Building2 />
        </div>
        <h1 id="no-company-title" className={styles.title}>No active company access</h1>
        <p className={styles.text}>
          Your account{user?.email ? <> (<strong>{user.email}</strong>)</> : null} isn’t linked to an active company. Your membership may have been
          deactivated, or you haven’t been assigned to a company yet.
        </p>
        <p className={styles.text}>Contact your company administrator to restore access.</p>
        <div className={styles.actions}>
          {companies.map((c) => (
            <Button key={c.id} variant="secondary" leftIcon={<Building2 size={16} />} onClick={() => void switchCompany(c.id)}>
              Open {c.name}
            </Button>
          ))}
          <Button variant="primary" leftIcon={<LogOut size={16} />} onClick={() => void logout()}>
            Sign out
          </Button>
        </div>
      </main>
    </div>
  );
}
