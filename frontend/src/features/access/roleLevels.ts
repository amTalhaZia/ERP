import type { LucideIcon } from 'lucide-react';
import {
  Briefcase, Calculator, Crown, Eye, FileText, FolderKanban, LayoutDashboard, Package, ShieldCheck, ShoppingCart, Sparkles, TrendingUp, User, Users, Wallet, Building2, KeyRound, ClipboardList,
} from 'lucide-react';

export type RoleTone = 'top' | 'head' | 'staff' | 'custom';

export const ROLE_ICONS: Record<string, LucideIcon> = {
  'company.owner': Crown,
  'company.admin': ShieldCheck,
  'hr.manager': Users,
  accountant: Calculator,
  'sales.manager': TrendingUp,
  'inventory.manager': Package,
  'procurement.officer': ShoppingCart,
  'department.manager': Briefcase,
  auditor: Eye,
  employee: User,
};

export const roleIcon = (slug: string): LucideIcon => ROLE_ICONS[slug] ?? Sparkles;

/** Roles grouped by level so the most powerful sit on top and everything reads top-down. */
export const ROLE_GROUPS: { key: string; tone: RoleTone; title: string; hint: string; slugs: readonly string[] }[] = [
  { key: 'top', tone: 'top', title: 'Top level', hint: 'Runs the whole company', slugs: ['company.owner', 'company.admin'] },
  { key: 'heads', tone: 'head', title: 'Department heads', hint: 'Look after one area of the business', slugs: ['hr.manager', 'accountant', 'sales.manager', 'inventory.manager', 'procurement.officer', 'department.manager'] },
  { key: 'staff', tone: 'staff', title: 'Staff & read-only', hint: 'Their own things, or view only', slugs: ['auditor', 'employee'] },
];

export const CUSTOM_GROUP = { key: 'custom', tone: 'custom' as RoleTone, title: 'Your own roles', hint: 'Created by your company', slugs: [] as readonly string[] };

/** Split roles into the level groups above (unknown slugs → "Your own roles"), keeping each group's order. */
export function groupRoles<T extends { slug: string }>(roles: T[]) {
  const known = new Set(ROLE_GROUPS.flatMap((g) => g.slugs));
  return [
    ...ROLE_GROUPS.map((g) => ({ ...g, items: g.slugs.flatMap((s) => roles.filter((r) => r.slug === s)) })),
    { ...CUSTOM_GROUP, items: roles.filter((r) => !known.has(r.slug)) },
  ].filter((g) => g.items.length > 0);
}

/** Everyday names for permission areas, for people who don't know the technical module names. */
export const AREA_LABELS: Record<string, { label: string; hint: string; icon: LucideIcon }> = {
  dashboard: { label: 'Home dashboard', hint: 'Company numbers and charts on the home screen', icon: LayoutDashboard },
  company: { label: 'Company & branches', hint: 'Company details, branches and departments', icon: Building2 },
  access: { label: 'Users & roles', hint: 'Who can sign in and what they are allowed to do', icon: KeyRound },
  hr: { label: 'Staff (HR)', hint: 'Employees, attendance, leave and reviews', icon: Users },
  payroll: { label: 'Salaries (Payroll)', hint: 'Monthly salary runs and payslips', icon: Wallet },
  finance: { label: 'Accounts & money', hint: 'Invoices, bills, payments and the books', icon: Calculator },
  sales: { label: 'Sales & customers', hint: 'Customers, quotations and sales orders', icon: TrendingUp },
  procurement: { label: 'Buying (Purchases)', hint: 'Suppliers, purchase requests and orders', icon: ShoppingCart },
  inventory: { label: 'Stock & warehouses', hint: 'Products, warehouses and stock movement', icon: Package },
  projects: { label: 'Projects & tasks', hint: 'Projects, tasks and time tracking', icon: FolderKanban },
  documents: { label: 'Documents & files', hint: 'Shared folders and uploaded files', icon: FileText },
  system: { label: 'Reports, approvals & history', hint: 'Reports, approval inbox and the activity log', icon: ClipboardList },
};
