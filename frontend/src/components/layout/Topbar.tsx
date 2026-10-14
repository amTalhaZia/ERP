'use client';

import { useEffect, useRef, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { Bell, ChevronDown, LogOut, Menu as MenuIcon, Moon, Search, Sun, Monitor, Check, Building2, Contrast } from 'lucide-react';
import clsx from 'clsx';
import { useQuery } from '@tanstack/react-query';
import { useAppDispatch, useAppSelector } from '@/store';
import { setMobileNavOpen, setTheme, setHighContrast, setBranchId, type ThemeMode } from '@/store/uiSlice';
import { selectBranches, selectCompanies, selectCompany, selectUser } from '@/store/sessionSlice';
import { useSession } from '@/lib/auth/SessionProvider';
import { findNavTrail, userMenuItems } from '@/lib/navigation';
import { get } from '@/lib/api/client';
import { authApi } from '@/features/auth/api';
import styles from './Topbar.module.scss';

function useClickAway(onAway: () => void) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onAway();
    };
    const esc = (e: KeyboardEvent) => e.key === 'Escape' && onAway();
    document.addEventListener('mousedown', handler);
    document.addEventListener('keydown', esc);
    return () => {
      document.removeEventListener('mousedown', handler);
      document.removeEventListener('keydown', esc);
    };
  }, [onAway]);
  return ref;
}

function Initials({ name }: { name: string }) {
  const initials = name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase())
    .join('');
  return <span className={styles.avatar} aria-hidden>{initials}</span>;
}

export function Topbar() {
  const dispatch = useAppDispatch();
  const router = useRouter();
  const pathname = usePathname();
  const user = useAppSelector(selectUser);
  const company = useAppSelector(selectCompany);
  const companies = useAppSelector(selectCompanies);
  const branches = useAppSelector(selectBranches);
  const branchId = useAppSelector((s) => s.ui.branchId);
  const theme = useAppSelector((s) => s.ui.theme);
  const highContrast = useAppSelector((s) => s.ui.highContrast);
  const { logout, switchCompany } = useSession();

  const [openMenu, setOpenMenu] = useState<'company' | 'branch' | 'user' | null>(null);
  const close = () => setOpenMenu(null);
  const menuRef = useClickAway(close);
  const [switching, setSwitching] = useState(false);

  const trail = findNavTrail(pathname);
  const title = trail.at(-1)?.label ?? 'Enterprise ERP';

  const { data: unread } = useQuery({
    queryKey: ['notifications', 'unread-count', company?.id],
    queryFn: () => get<{ data: { unread: number } }>('/notifications/unread-count').then((r) => r.data.unread),
    refetchInterval: 60_000,
    enabled: !!user,
  });

  const changeTheme = (mode: ThemeMode) => {
    dispatch(setTheme(mode));
    void authApi.updatePreferences({ theme: mode }).catch(() => undefined);
  };
  const toggleContrast = () => {
    dispatch(setHighContrast(!highContrast));
    void authApi.updatePreferences({ high_contrast: !highContrast }).catch(() => undefined);
  };

  const onSwitch = async (id: number) => {
    if (id === company?.id) return close();
    setSwitching(true);
    try {
      await switchCompany(id);
    } finally {
      setSwitching(false);
      close();
    }
  };

  return (
    <header className={styles.topbar}>
      <div className={styles.left}>
        <button type="button" className={styles.menuBtn} onClick={() => dispatch(setMobileNavOpen(true))} aria-label="Open menu">
          <MenuIcon size={20} />
        </button>

        <nav aria-label="Breadcrumb" className={styles.breadcrumbs}>
          <ol>
            <li>
              <Link href="/dashboard">Home</Link>
            </li>
            {trail.map((item, i) => (
              <li key={item.href} aria-current={i === trail.length - 1 ? 'page' : undefined}>
                {i === trail.length - 1 ? <span>{item.label}</span> : <Link href={item.href}>{item.label}</Link>}
              </li>
            ))}
          </ol>
        </nav>
        <h1 className={styles.mobileTitle}>{title}</h1>
      </div>

      <div className={styles.center}>
        <button
          type="button"
          className={styles.search}
          onClick={() => router.push('/search')}
          aria-label="Global search"
        >
          <Search size={16} aria-hidden />
          <span>Search…</span>
          <kbd>⌘K</kbd>
        </button>
      </div>

      <div className={styles.right} ref={menuRef}>
        {/* Company switcher */}
        {companies.length > 0 && (
          <div className={styles.menuWrap}>
            <button
              type="button"
              className={clsx(styles.pill, openMenu === 'company' && styles.pillOpen)}
              onClick={() => setOpenMenu(openMenu === 'company' ? null : 'company')}
              aria-haspopup="menu"
              aria-expanded={openMenu === 'company'}
              disabled={switching}
            >
              <Building2 size={15} aria-hidden />
              <span className={styles.pillLabel}>{company?.name ?? 'Select company'}</span>
              <ChevronDown size={14} aria-hidden />
            </button>
            {openMenu === 'company' && (
              <div className={styles.menu} role="menu" aria-label="Switch company">
                <div className={styles.menuHeading}>Companies</div>
                {companies.map((c) => (
                  <button key={c.id} type="button" role="menuitemradio" aria-checked={c.id === company?.id} className={styles.menuItem} onClick={() => onSwitch(c.id)}>
                    <span className={styles.menuItemText}>
                      <span>{c.name}</span>
                      <small>{c.role || 'Member'}</small>
                    </span>
                    {c.id === company?.id && <Check size={16} aria-hidden />}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Branch filter */}
        {branches.length > 1 && (
          <div className={clsx(styles.menuWrap, styles.hideSm)}>
            <button
              type="button"
              className={clsx(styles.pill, openMenu === 'branch' && styles.pillOpen)}
              onClick={() => setOpenMenu(openMenu === 'branch' ? null : 'branch')}
              aria-haspopup="menu"
              aria-expanded={openMenu === 'branch'}
            >
              <span className={styles.pillLabel}>{branches.find((b) => b.id === branchId)?.name ?? 'All branches'}</span>
              <ChevronDown size={14} aria-hidden />
            </button>
            {openMenu === 'branch' && (
              <div className={styles.menu} role="menu" aria-label="Filter by branch">
                <button type="button" role="menuitemradio" aria-checked={branchId === null} className={styles.menuItem} onClick={() => { dispatch(setBranchId(null)); close(); }}>
                  <span>All branches</span>
                  {branchId === null && <Check size={16} aria-hidden />}
                </button>
                {branches.map((b) => (
                  <button key={b.id} type="button" role="menuitemradio" aria-checked={branchId === b.id} className={styles.menuItem} onClick={() => { dispatch(setBranchId(b.id)); close(); }}>
                    <span className={styles.menuItemText}>
                      <span>{b.name}</span>
                      <small>{b.code}{b.is_head_office ? ' · Head office' : ''}</small>
                    </span>
                    {branchId === b.id && <Check size={16} aria-hidden />}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Notifications */}
        <Link href="/notifications" className={styles.iconBtn} aria-label={`Notifications${unread ? `, ${unread} unread` : ''}`}>
          <Bell size={18} />
          {!!unread && <span className={styles.badge}>{unread > 99 ? '99+' : unread}</span>}
        </Link>

        {/* User menu */}
        <div className={styles.menuWrap}>
          <button
            type="button"
            className={clsx(styles.userBtn, openMenu === 'user' && styles.pillOpen)}
            onClick={() => setOpenMenu(openMenu === 'user' ? null : 'user')}
            aria-haspopup="menu"
            aria-expanded={openMenu === 'user'}
            aria-label="Account menu"
          >
            {user?.avatar_url ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={user.avatar_url} alt="" className={styles.avatarImg} />
            ) : (
              <Initials name={user?.name ?? '?'} />
            )}
            <ChevronDown size={14} aria-hidden className={styles.hideSm} />
          </button>
          {openMenu === 'user' && (
            <div className={clsx(styles.menu, styles.menuRight)} role="menu" aria-label="Account">
              <div className={styles.userHead}>
                <div className={styles.userName}>{user?.name}</div>
                <div className={styles.userEmail}>{user?.email}</div>
              </div>
              {userMenuItems.map((item) => {
                const Icon = item.icon;
                return (
                  <Link key={item.href} href={item.href} role="menuitem" className={styles.menuItem} onClick={close}>
                    {Icon && <Icon size={16} aria-hidden />}
                    <span>{item.label}</span>
                  </Link>
                );
              })}
              <div className={styles.menuHeading}>Theme</div>
              <div className={styles.themeRow} role="radiogroup" aria-label="Theme">
                {(
                  [
                    ['light', Sun, 'Light'],
                    ['dark', Moon, 'Dark'],
                    ['system', Monitor, 'System'],
                  ] as const
                ).map(([mode, Icon, label]) => (
                  <button key={mode} type="button" role="radio" aria-checked={theme === mode} className={clsx(styles.themeBtn, theme === mode && styles.themeBtnActive)} onClick={() => changeTheme(mode)}>
                    <Icon size={15} aria-hidden />
                    <span>{label}</span>
                  </button>
                ))}
              </div>
              <button type="button" role="menuitemcheckbox" aria-checked={highContrast} className={styles.menuItem} onClick={toggleContrast}>
                <Contrast size={16} aria-hidden />
                <span>High contrast</span>
                {highContrast && <Check size={16} aria-hidden />}
              </button>
              <div className={styles.menuDivider} />
              <button type="button" role="menuitem" className={clsx(styles.menuItem, styles.danger)} onClick={() => void logout()}>
                <LogOut size={16} aria-hidden />
                <span>Sign out</span>
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
