import type { ReactNode } from 'react';
import { Hexagon, ShieldCheck, Building2, BarChart3 } from 'lucide-react';
import styles from './layout.module.scss';

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className={styles.wrap}>
      <aside className={styles.brandPanel} aria-hidden>
        <div className={styles.brandTop}>
          <Hexagon size={28} strokeWidth={2.25} />
          <span>Enterprise ERP</span>
        </div>
        <div className={styles.brandBody}>
          <h2>One workspace for every company you run.</h2>
          <ul>
            <li>
              <Building2 size={18} />
              <span>Multi-company, multi-branch, strictly isolated</span>
            </li>
            <li>
              <BarChart3 size={18} />
              <span>Finance, HR, sales, inventory and projects — one ledger of truth</span>
            </li>
            <li>
              <ShieldCheck size={18} />
              <span>Role-based access, two-factor sign-in, full audit trail</span>
            </li>
          </ul>
        </div>
        <div className={styles.brandFoot}>© {new Date().getFullYear()} Enterprise ERP</div>
      </aside>
      <main className={styles.formPanel}>
        <div className={styles.formInner}>{children}</div>
      </main>
    </div>
  );
}
