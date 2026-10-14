import type { LucideIcon } from 'lucide-react';
import {
  LayoutDashboard, Building2, Users, UserCog, CalendarCheck, Wallet, Landmark, BookOpen, Receipt,
  FileText, CreditCard, ShoppingCart, Contact, Target, FileSignature, Truck, PackageSearch, Boxes,
  Warehouse, ArrowLeftRight, FolderKanban, Files, Bell, BarChart3, ShieldCheck, Settings, Globe, Server,
  ClipboardList, Briefcase, Map,
} from 'lucide-react';
import type { Permission } from '@/lib/auth/permissions';

export interface NavItem {
  label: string;
  href: string;
  icon?: LucideIcon;
  /** Any of these grants visibility */
  permission?: Permission | Permission[];
  children?: NavItem[];
  /** Match nested routes for active state */
  exact?: boolean;
  platform?: boolean;
}

export interface NavSection {
  title?: string;
  items: NavItem[];
}

export const navigation: NavSection[] = [
  {
    items: [
      { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, permission: 'dashboard.view', exact: true },
      { label: 'Approvals', href: '/approvals', icon: ClipboardList, permission: 'approvals.view' },
    ],
  },
  {
    title: 'Organisation',
    items: [
      {
        label: 'Company',
        href: '/company',
        icon: Building2,
        permission: ['company.view', 'branches.view', 'departments.view'],
        children: [
          { label: 'Profile & settings', href: '/company', permission: 'company.view', exact: true },
          { label: 'Branches', href: '/company/branches', permission: 'branches.view' },
          { label: 'Departments', href: '/company/departments', permission: 'departments.view' },
          { label: 'Locations map', href: '/company/map', permission: 'branches.view' },
        ],
      },
      {
        label: 'Users & roles',
        href: '/access',
        icon: UserCog,
        permission: ['users.view', 'roles.view'],
        children: [
          { label: 'Users', href: '/access/users', permission: 'users.view' },
          { label: 'Roles & permissions', href: '/access/roles', permission: 'roles.view' },
        ],
      },
    ],
  },
  {
    title: 'People',
    items: [
      {
        label: 'HR',
        href: '/hr',
        icon: Users,
        permission: ['employees.view', 'attendance.view', 'leave.view', 'leave.request', 'performance.view'],
        children: [
          { label: 'Employees', href: '/hr/employees', permission: 'employees.view' },
          { label: 'Org chart', href: '/hr/org-chart', permission: 'employees.view' },
          { label: 'Attendance', href: '/hr/attendance', permission: ['attendance.view', 'attendance.manage'] },
          { label: 'Leave', href: '/hr/leave', permission: ['leave.view', 'leave.request'] },
          { label: 'Shifts & holidays', href: '/hr/shifts', permission: 'shifts.manage' },
          { label: 'Performance', href: '/hr/performance', permission: 'performance.view' },
          { label: 'HR reports', href: '/hr/reports', permission: 'employees.view' },
        ],
      },
      {
        label: 'Payroll',
        href: '/payroll',
        icon: Wallet,
        permission: ['payroll.view', 'payslips.view.own'],
        children: [
          { label: 'Payroll runs', href: '/payroll/runs', permission: 'payroll.view' },
          { label: 'Salary structures', href: '/payroll/structures', permission: 'payroll.manage' },
          { label: 'My payslips', href: '/payroll/my-payslips', permission: 'payslips.view.own' },
        ],
      },
    ],
  },
  {
    title: 'Finance',
    items: [
      {
        label: 'Accounting',
        href: '/finance',
        icon: Landmark,
        permission: ['accounts.view', 'journals.view', 'reports.financial.view'],
        children: [
          { label: 'Chart of accounts', href: '/finance/accounts', permission: 'accounts.view' },
          { label: 'Journal entries', href: '/finance/journals', permission: 'journals.view' },
          { label: 'Bank & cash', href: '/finance/bank', permission: ['bank.manage', 'accounts.view'] },
          { label: 'Tax rates', href: '/finance/tax', permission: 'tax.manage' },
          { label: 'Financial periods', href: '/finance/periods', permission: 'periods.manage' },
          { label: 'Financial reports', href: '/finance/reports', permission: 'reports.financial.view' },
        ],
      },
      {
        label: 'Receivables',
        href: '/finance/invoices',
        icon: Receipt,
        permission: ['invoices.view', 'payments.view'],
        children: [
          { label: 'Customer invoices', href: '/finance/invoices', permission: 'invoices.view' },
          { label: 'Receipts', href: '/finance/receipts', permission: 'payments.view' },
        ],
      },
      {
        label: 'Payables',
        href: '/finance/bills',
        icon: CreditCard,
        permission: ['bills.view', 'payments.view', 'expenses.view', 'expenses.submit'],
        children: [
          { label: 'Supplier bills', href: '/finance/bills', permission: 'bills.view' },
          { label: 'Payments', href: '/finance/payments', permission: 'payments.view' },
          { label: 'Expense claims', href: '/finance/expenses', permission: ['expenses.view', 'expenses.submit'] },
        ],
      },
    ],
  },
  {
    title: 'Operations',
    items: [
      {
        label: 'Sales & CRM',
        href: '/sales',
        icon: Target,
        permission: ['customers.view', 'leads.view', 'quotations.view', 'sales_orders.view'],
        children: [
          { label: 'Customers', href: '/sales/customers', permission: 'customers.view' },
          { label: 'Leads', href: '/sales/leads', permission: 'leads.view' },
          { label: 'Pipeline', href: '/sales/pipeline', permission: 'leads.view' },
          { label: 'Quotations', href: '/sales/quotations', permission: 'quotations.view' },
          { label: 'Sales orders', href: '/sales/orders', permission: 'sales_orders.view' },
          { label: 'Deliveries', href: '/sales/deliveries', permission: 'sales_orders.view' },
          { label: 'Sales reports', href: '/sales/reports', permission: 'reports.view' },
        ],
      },
      {
        label: 'Procurement',
        href: '/procurement',
        icon: ShoppingCart,
        permission: ['suppliers.view', 'requisitions.view', 'requisitions.create', 'purchase_orders.view'],
        children: [
          { label: 'Suppliers', href: '/procurement/suppliers', permission: 'suppliers.view' },
          { label: 'Requisitions', href: '/procurement/requisitions', permission: ['requisitions.view', 'requisitions.create'] },
          { label: 'Purchase orders', href: '/procurement/orders', permission: 'purchase_orders.view' },
          { label: 'Goods receipts', href: '/procurement/receipts', permission: 'purchase_orders.view' },
          { label: 'Procurement reports', href: '/procurement/reports', permission: 'reports.view' },
        ],
      },
      {
        label: 'Inventory',
        href: '/inventory',
        icon: Boxes,
        permission: ['products.view', 'inventory.view', 'warehouses.view'],
        children: [
          { label: 'Products', href: '/inventory/products', permission: 'products.view' },
          { label: 'Stock levels', href: '/inventory/stock', permission: 'inventory.view' },
          { label: 'Movements', href: '/inventory/movements', permission: 'inventory.view' },
          { label: 'Transfers', href: '/inventory/transfers', permission: 'inventory.view' },
          { label: 'Adjustments', href: '/inventory/adjustments', permission: 'inventory.view' },
          { label: 'Warehouses', href: '/inventory/warehouses', permission: 'warehouses.view' },
          { label: 'Inventory reports', href: '/inventory/reports', permission: 'inventory.view' },
        ],
      },
      {
        label: 'Projects',
        href: '/projects',
        icon: FolderKanban,
        permission: ['projects.view', 'tasks.view'],
      },
      { label: 'Documents', href: '/documents', icon: Files, permission: 'documents.view' },
    ],
  },
  {
    title: 'Insights',
    items: [
      { label: 'Reports & exports', href: '/reports', icon: BarChart3, permission: 'reports.view' },
      { label: 'Audit log', href: '/audit', icon: ShieldCheck, permission: 'audit.view' },
    ],
  },
  {
    title: 'Platform',
    items: [
      { label: 'Companies', href: '/platform/companies', icon: Globe, permission: 'platform.companies.manage', platform: true },
      { label: 'Platform users', href: '/platform/users', icon: Briefcase, permission: 'platform.users.manage', platform: true },
      { label: 'System', href: '/platform/system', icon: Server, permission: 'platform.system.view', platform: true },
    ],
  },
];

export const userMenuItems: NavItem[] = [
  { label: 'My profile', href: '/settings/profile', icon: Contact },
  { label: 'Security', href: '/settings/security', icon: ShieldCheck },
  { label: 'Notifications', href: '/settings/notifications', icon: Bell },
  { label: 'Appearance', href: '/settings/appearance', icon: Settings },
];

// Unused-icon guard so tree-shaking keeps the set stable for later modules.
export const iconRegistry = { CalendarCheck, BookOpen, FileText, FileSignature, Truck, PackageSearch, Warehouse, ArrowLeftRight, Map };

/**
 * Flatten for breadcrumbs / page titles. Picks the most specific match (longest
 * href), so `/finance/invoices/12` resolves to Receivables › Customer invoices
 * rather than the `/finance` (Accounting) prefix that appears earlier.
 */
export function findNavTrail(pathname: string): NavItem[] {
  let best: NavItem[] = [];
  const walk = (items: NavItem[], acc: NavItem[]) => {
    for (const item of items) {
      if (item.children) walk(item.children, [...acc, item]);
      const matches = item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(item.href + '/');
      const current = best[best.length - 1];
      // Strictly longer wins; on a tie the earlier (child-first) match is kept.
      if (matches && (!current || item.href.length > current.href.length)) best = [...acc, item];
    }
  };
  for (const section of navigation) walk(section.items, []);
  return best;
}
