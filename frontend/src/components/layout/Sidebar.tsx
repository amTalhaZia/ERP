'use client';

import { useMemo, useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { ChevronDown, ChevronsLeft, ChevronsRight, Hexagon, X } from 'lucide-react';
import clsx from 'clsx';
import { navigation, type NavItem } from '@/lib/navigation';
import { useCan } from '@/lib/auth/useCan';
import { useAppDispatch, useAppSelector } from '@/store';
import { setMobileNavOpen, toggleSidebar } from '@/store/uiSlice';
import { selectCompany, selectIsPlatformAdmin } from '@/store/sessionSlice';
import styles from './Sidebar.module.scss';

function isActive(item: NavItem, pathname: string) {
  return item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(item.href + '/');
}

export function Sidebar() {
  const pathname = usePathname();
  const can = useCan();
  const dispatch = useAppDispatch();
  const collapsed = useAppSelector((s) => s.ui.sidebarCollapsed);
  const mobileOpen = useAppSelector((s) => s.ui.mobileNavOpen);
  const company = useAppSelector(selectCompany);
  const isPlatformAdmin = useAppSelector(selectIsPlatformAdmin);
  const reduce = useReducedMotion();
  const [open, setOpen] = useState<Record<string, boolean>>({});

  const sections = useMemo(
    () =>
      navigation
        .map((section) => ({
          ...section,
          items: section.items
            .filter((item) => (item.platform ? isPlatformAdmin : can(item.permission, 'any')))
            .map((item) => ({
              ...item,
              children: item.children?.filter((c) => can(c.permission, 'any')),
            }))
            .filter((item) => !item.children || item.children.length > 0),
        }))
        .filter((s) => s.items.length > 0),
    [can, isPlatformAdmin],
  );

  // A group is open if the user toggled it open, or (untoggled) it contains the current route.
  const isGroupOpen = (item: NavItem) => open[item.href] ?? isActive(item, pathname);

  // Close the mobile drawer on navigation.
  useEffect(() => {
    dispatch(setMobileNavOpen(false));
  }, [pathname, dispatch]);

  const content = (
    <nav className={clsx(styles.nav, collapsed && styles.collapsed)} aria-label="Main navigation">
      <div className={styles.brand}>
        <Link href="/dashboard" className={styles.brandLink} aria-label="Enterprise ERP home">
          {company?.logo_url ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={company.logo_url} alt="" className={styles.logo} />
          ) : (
            <Hexagon className={styles.logoIcon} size={22} strokeWidth={2.25} aria-hidden />
          )}
          {!collapsed && (
            <span className={styles.brandText}>
              <span className={styles.brandName}>{company?.name ?? 'Enterprise ERP'}</span>
              <span className={styles.brandSub}>ERP</span>
            </span>
          )}
        </Link>
        <button type="button" className={styles.mobileClose} onClick={() => dispatch(setMobileNavOpen(false))} aria-label="Close menu">
          <X size={18} />
        </button>
      </div>

      <div className={styles.scroll}>
        {sections.map((section, i) => (
          <div key={section.title ?? i} className={styles.section}>
            {section.title && !collapsed && <div className={styles.sectionTitle}>{section.title}</div>}
            {section.title && collapsed && <div className={styles.sectionRule} aria-hidden />}
            <ul className={styles.list}>
              {section.items.map((item) => {
                const Icon = item.icon;
                const active = isActive(item, pathname);
                const hasChildren = !!item.children?.length;
                const expanded = isGroupOpen(item);

                return (
                  <li key={item.href}>
                    {hasChildren && !collapsed ? (
                      <button
                        type="button"
                        className={clsx(styles.item, active && styles.itemActiveParent)}
                        onClick={() => setOpen((o) => ({ ...o, [item.href]: !expanded }))}
                        aria-expanded={expanded}
                        aria-controls={`nav-group-${item.href}`}
                      >
                        {Icon && <Icon size={18} className={styles.icon} aria-hidden />}
                        <span className={styles.label}>{item.label}</span>
                        <ChevronDown size={16} className={clsx(styles.chevron, expanded && styles.chevronOpen)} aria-hidden />
                      </button>
                    ) : (
                      <Link
                        href={item.href}
                        className={clsx(styles.item, active && styles.itemActive)}
                        aria-current={active ? 'page' : undefined}
                        title={collapsed ? item.label : undefined}
                      >
                        {Icon && <Icon size={18} className={styles.icon} aria-hidden />}
                        {!collapsed && <span className={styles.label}>{item.label}</span>}
                      </Link>
                    )}

                    {hasChildren && !collapsed && (
                      <AnimatePresence initial={false}>
                        {expanded && (
                          <motion.ul
                            id={`nav-group-${item.href}`}
                            className={styles.children}
                            initial={reduce ? false : { height: 0, opacity: 0 }}
                            animate={{ height: 'auto', opacity: 1 }}
                            exit={reduce ? undefined : { height: 0, opacity: 0 }}
                            transition={{ duration: 0.18, ease: [0.2, 0, 0, 1] }}
                          >
                            {item.children!.map((child) => {
                              const childActive = isActive(child, pathname);
                              return (
                                <li key={child.href}>
                                  <Link
                                    href={child.href}
                                    className={clsx(styles.child, childActive && styles.childActive)}
                                    aria-current={childActive ? 'page' : undefined}
                                  >
                                    <span className={styles.childDot} aria-hidden />
                                    {child.label}
                                  </Link>
                                </li>
                              );
                            })}
                          </motion.ul>
                        )}
                      </AnimatePresence>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>

      <div className={styles.footer}>
        <button
          type="button"
          className={styles.collapseBtn}
          onClick={() => dispatch(toggleSidebar())}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          aria-pressed={collapsed}
        >
          {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
          {!collapsed && <span>Collapse</span>}
        </button>
      </div>
    </nav>
  );

  return (
    <>
      <aside className={clsx(styles.desktop, collapsed && styles.desktopCollapsed)}>{content}</aside>

      <AnimatePresence>
        {mobileOpen && (
          <>
            <motion.div
              className={styles.backdrop}
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.15 }}
              onClick={() => dispatch(setMobileNavOpen(false))}
              aria-hidden
            />
            <motion.aside
              className={styles.mobile}
              role="dialog"
              aria-modal="true"
              aria-label="Navigation menu"
              initial={reduce ? false : { x: '-100%' }}
              animate={{ x: 0 }}
              exit={reduce ? undefined : { x: '-100%' }}
              transition={{ duration: 0.22, ease: [0.2, 0, 0, 1] }}
            >
              {content}
            </motion.aside>
          </>
        )}
      </AnimatePresence>
    </>
  );
}
