'use client';

import type { ReactNode } from 'react';
import clsx from 'clsx';
import { Sidebar } from '@/components/layout/Sidebar';
import { Topbar } from '@/components/layout/Topbar';
import { useAppSelector } from '@/store';
import { selectSession } from '@/store/sessionSlice';
import { AppLoading } from '@/components/layout/AppLoading';
import { NoCompanyAccess, RequirePermission } from '@/components/layout/RouteGuard';
import styles from './layout.module.scss';

export default function AppLayout({ children }: { children: ReactNode }) {
  const session = useAppSelector(selectSession);
  const collapsed = useAppSelector((s) => s.ui.sidebarCollapsed);

  if (session.status === 'unknown' || session.status === 'loading') {
    return <AppLoading />;
  }
  if (session.status !== 'authenticated') {
    // SessionProvider is redirecting to /login.
    return <AppLoading label="Redirecting to sign in…" />;
  }
  // Signed in but no active membership (deactivated / never assigned). Platform
  // staff are exempt — they work from the platform console without a company.
  if (!session.current_company && !session.user?.is_platform_admin) {
    return <NoCompanyAccess />;
  }

  return (
    <div className={clsx(styles.shell, collapsed && styles.shellCollapsed)}>
      <Sidebar />
      <div className={styles.main}>
        <Topbar />
        <main id="main" className={styles.content} tabIndex={-1}>
          <RequirePermission>{children}</RequirePermission>
        </main>
      </div>
    </div>
  );
}
