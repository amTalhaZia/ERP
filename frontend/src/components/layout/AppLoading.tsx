import { Hexagon } from 'lucide-react';
import styles from './AppLoading.module.scss';

export function AppLoading({ label = 'Loading your workspace…' }: { label?: string }) {
  return (
    <div className={styles.wrap} role="status" aria-live="polite">
      <Hexagon className={styles.icon} size={36} strokeWidth={2} aria-hidden />
      <div className={styles.bar}>
        <span />
      </div>
      <p>{label}</p>
    </div>
  );
}
