// Readable role × module matrix computed from guide-roles.json, which is
// exported from the database (actual seeded role permissions) — see the
// tinker command in the build notes. Falls back to config templates if the
// "actual" section is missing.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const data = JSON.parse(fs.readFileSync(path.join(HERE, '..', 'guide-roles.json'), 'utf8'));

export const catalogue = data.catalogue; // module -> { slug: description }
const allSlugs = Object.entries(catalogue).filter(([m]) => m !== 'platform').flatMap(([, p]) => Object.keys(p));

function expandTemplate(perms) {
  const out = new Set();
  for (const p of perms) {
    if (p.startsWith('!')) continue;
    if (p === '*') allSlugs.forEach((s) => out.add(s));
    else if (p.startsWith('*.')) allSlugs.filter((s) => s.endsWith(p.slice(1))).forEach((s) => out.add(s));
    else if (p.endsWith('.*')) allSlugs.filter((s) => s.startsWith(p.slice(0, -1))).forEach((s) => out.add(s));
    else out.add(p);
  }
  for (const p of perms) if (p.startsWith('!')) out.delete(p.slice(1));
  return out;
}

export const ROLE_ORDER = ['company.owner', 'company.admin', 'hr.manager', 'accountant', 'sales.manager', 'inventory.manager', 'procurement.officer', 'department.manager', 'employee', 'auditor'];
export const ROLE_SHORT = {
  'company.owner': 'Owner', 'company.admin': 'Admin', 'hr.manager': 'HR Mgr', accountant: 'Accountant', 'sales.manager': 'Sales Mgr',
  'inventory.manager': 'Inventory Mgr', 'procurement.officer': 'Procurement', 'department.manager': 'Dept Mgr', employee: 'Employee', auditor: 'Auditor',
};

export const roles = ROLE_ORDER.map((slug) => {
  const actual = data.actual?.[slug];
  const tpl = data.roles[slug];
  const perms = new Set(actual ? actual.perms : [...expandTemplate(tpl.permissions)]);
  return { slug, name: actual?.name ?? tpl.name, description: tpl.description, perms, short: ROLE_SHORT[slug] };
});

export const GROUPS = [
  ['Dashboard', ['dashboard.view']],
  ['Company profile & settings', ['company.view', 'company.settings.manage']],
  ['Branches & departments', ['branches.view', 'branches.manage', 'departments.view', 'departments.manage']],
  ['Users & roles', ['users.view', 'users.manage', 'roles.view', 'roles.manage']],
  ['Employees (HR records)', ['employees.view', 'employees.create', 'employees.update', 'employees.delete', 'employees.documents.view']],
  ['Attendance & shifts', ['attendance.view', 'attendance.manage', 'shifts.manage']],
  ['Leave', ['leave.view', 'leave.request', 'leave.approve', 'leave.manage']],
  ['Performance reviews', ['performance.view', 'performance.manage']],
  ['Payroll (runs & setup)', ['payroll.view', 'payroll.manage', 'payroll.process']],
  ['Own payslips', ['payslips.view.own']],
  ['Chart of accounts & journals', ['accounts.view', 'accounts.manage', 'journals.view', 'journals.create', 'journals.post']],
  ['Customer invoices', ['invoices.view', 'invoices.create', 'invoices.update', 'invoices.approve', 'invoices.delete']],
  ['Supplier bills', ['bills.view', 'bills.create', 'bills.approve']],
  ['Payments & receipts', ['payments.view', 'payments.create']],
  ['Expense claims', ['expenses.view', 'expenses.submit', 'expenses.approve']],
  ['Bank, tax & periods', ['bank.manage', 'tax.manage', 'periods.manage']],
  ['Financial reports', ['reports.financial.view']],
  ['Customers & leads (CRM)', ['customers.view', 'customers.manage', 'leads.view', 'leads.manage']],
  ['Quotations & sales orders', ['quotations.view', 'quotations.manage', 'sales_orders.view', 'sales_orders.manage', 'sales_orders.approve']],
  ['Suppliers & requisitions', ['suppliers.view', 'suppliers.manage', 'requisitions.view', 'requisitions.create', 'requisitions.approve']],
  ['Purchase orders & goods receipts', ['purchase_orders.view', 'purchase_orders.manage', 'purchase_orders.approve', 'goods_receipts.manage']],
  ['Products & warehouses', ['products.view', 'products.manage', 'warehouses.view', 'warehouses.manage']],
  ['Stock operations', ['inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.transfer', 'inventory.adjust']],
  ['Projects, tasks & time', ['projects.view', 'projects.manage', 'tasks.view', 'tasks.manage', 'time_entries.manage']],
  ['Documents', ['documents.view', 'documents.manage']],
  ['Approvals inbox', ['approvals.view']],
  ['Reports & exports', ['reports.view', 'reports.export']],
  ['Audit log', ['audit.view']],
];

/** ✓ full, 👁 view only, ◐ partial, — none */
export function cell(role, slugs) {
  const has = slugs.filter((s) => role.perms.has(s));
  if (!has.length) return '<span class="m-none">—</span>';
  if (has.length === slugs.length) return '<span class="m-full">✓</span>';
  if (has.every((s) => /\.view$/.test(s) || s === 'reports.financial.view')) return '<span class="m-view">👁</span>';
  return '<span class="m-part">◐</span>';
}

export function matrixTable() {
  const head = `<thead><tr><th>Module / area</th>${roles.map((r) => `<th>${r.short}</th>`).join('')}</tr></thead>`;
  const body = GROUPS.map(([label, slugs]) => `<tr><td>${label}</td>${roles.map((r) => `<td>${cell(r, slugs)}</td>`).join('')}</tr>`).join('');
  return `<table class="tbl matrix">${head}<tbody>${body}</tbody></table>`;
}

/** Partial cells explained: which actions each role has in a group. */
export function partialDetails() {
  const rows = [];
  for (const [label, slugs] of GROUPS) {
    for (const r of roles) {
      const has = slugs.filter((s) => r.perms.has(s));
      if (has.length && has.length < slugs.length && !has.every((s) => /\.view$/.test(s))) {
        rows.push([label, r.name, has.map((s) => describe(s)).join('; ')]);
      }
    }
  }
  return rows;
}

export function describe(slug) {
  for (const perms of Object.values(catalogue)) if (perms[slug]) return perms[slug];
  return slug;
}
