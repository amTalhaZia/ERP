/** Company roles ordered from most to least access — used to group and sort members. */
export const ROLE_ORDER = [
  'company.owner',
  'company.admin',
  'hr.manager',
  'accountant',
  'sales.manager',
  'inventory.manager',
  'procurement.officer',
  'department.manager',
  'auditor',
  'employee',
] as const;

export const ROLE_META: Record<string, { label: string; hint: string; tone: 'primary' | 'info' | 'success' | 'warning' | 'neutral' }> = {
  'company.owner': { label: 'Company Owner', hint: 'Full access to everything in the company', tone: 'primary' },
  'company.admin': { label: 'Company Administrator', hint: 'Everything except running payroll', tone: 'info' },
  'hr.manager': { label: 'HR Manager', hint: 'Employees, attendance, leave, payroll', tone: 'success' },
  accountant: { label: 'Accountant', hint: 'Accounts, invoices, bills, payments', tone: 'success' },
  'sales.manager': { label: 'Sales Manager', hint: 'Customers, quotations, sales orders', tone: 'success' },
  'inventory.manager': { label: 'Inventory Manager', hint: 'Products, warehouses, stock', tone: 'success' },
  'procurement.officer': { label: 'Procurement Officer', hint: 'Suppliers, purchase orders', tone: 'success' },
  'department.manager': { label: 'Department Manager', hint: 'Team approvals and projects', tone: 'warning' },
  auditor: { label: 'Read-Only Auditor', hint: 'Can view everything, change nothing', tone: 'neutral' },
  employee: { label: 'Employee', hint: 'Self-service only', tone: 'neutral' },
};

/** Rank of a member's highest role (lower = more access). Custom roles sort after system roles. */
export function roleRank(slugs: string[]): number {
  const ranks = slugs.map((s) => {
    const i = (ROLE_ORDER as readonly string[]).indexOf(s);
    return i === -1 ? ROLE_ORDER.length - 1.5 : i;
  });
  return ranks.length ? Math.min(...ranks) : ROLE_ORDER.length;
}

export function primaryRoleSlug(slugs: string[]): string | null {
  if (!slugs.length) return null;
  return [...slugs].sort((a, b) => roleRank([a]) - roleRank([b]))[0] ?? null;
}
