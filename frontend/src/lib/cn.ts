/**
 * Class-name composer. Thin re-export of clsx so components never import the
 * library directly (lets us swap implementations later without churn).
 *
 * @example cn(styles.root, styles[size], { [styles.active]: active }, className)
 */
export { clsx as cn, type ClassValue } from 'clsx';
