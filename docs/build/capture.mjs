// Captures the screenshots used by the User & Admin Guide.
//
//   node capture.mjs              # everything
//   node capture.mjs owner-hr     # only shots whose name contains "owner-hr"
//
// Logs in through the real UI (one fresh browser context per user), visits
// pages and saves PNGs to ./screenshots. Never submits data-changing forms:
// drawers are opened and photographed, then discarded.
// Every page is checked for visible error / empty-skeleton states; findings
// are written to ./screenshots/_issues.json and printed at the end.

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(HERE, 'screenshots');
const BASE = process.env.ERP_URL ?? 'http://localhost:3000';
const PASSWORD = process.env.ERP_DEMO_PASSWORD ?? 'password';
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const FILTER = process.argv[2] ?? '';

fs.mkdirSync(OUT, { recursive: true });

// Hide the Next.js development indicator so it does not appear in screenshots.
const HIDE_DEV = () => {
  const add = () => {
    const st = document.createElement('style');
    st.textContent = 'nextjs-portal{display:none!important}';
    (document.head || document.documentElement).appendChild(st);
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add); else add();
};

const issues = [];
const captured = [];
const skipped = [];

const ERROR_TEXTS = [
  'Something went wrong',
  'This page couldn’t load',
  "This page couldn't load",
  'Page not found',
  'Dashboard unavailable',
];

async function settle(page, extra = 800) {
  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  // Wait (bounded) for skeleton placeholders to disappear.
  for (let i = 0; i < 40; i++) {
    const n = await visibleSkeletons(page);
    if (!n) break;
    await page.waitForTimeout(500);
  }
  await page.waitForTimeout(extra);
}

function visibleSkeletons(page) {
  return page.evaluate(() =>
    [...document.querySelectorAll('[class*="skeleton" i]')].filter((el) => {
      const r = el.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    }).length,
  ).catch(() => 0);
}

async function checkState(page, shot, user) {
  const text = await page.evaluate(() => document.body.innerText).catch(() => '');
  const found = ERROR_TEXTS.filter((t) => text.includes(t));
  const skeletons = await visibleSkeletons(page);
  if ((found.length || skeletons) && !shot.expectError) {
    const detail = text.split('\n').filter((l) => ERROR_TEXTS.some((t) => l.includes(t)) || /forbidden|permission|tenant\./i.test(l)).slice(0, 4).join(' / ');
    issues.push({ shot: shot.name, url: page.url().replace(BASE, ''), login: user, errors: found, skeletons, detail });
  }
}

async function login(browser, email, viewport = { width: 1440, height: 900 }) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 2, locale: 'en-US' });
  await context.addInitScript(HIDE_DEV);
  const page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('#email', email);
  await page.fill('#password', PASSWORD);
  await page.click('button[type="submit"]');
  for (let i = 0; i < 90 && /\/login/.test(page.url()); i++) await page.waitForTimeout(500);
  if (/\/login/.test(page.url())) throw new Error(`Login failed for ${email}`);
  await settle(page);
  return { context, page };
}

async function shoot(page, shot, user) {
  if (FILTER && !shot.name.includes(FILTER)) return;
  try {
    if (shot.url) await page.goto(BASE + shot.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await settle(page, shot.wait ?? 800);
    if (shot.action) {
      await shot.action(page);
      await settle(page, shot.afterWait ?? 900);
    }
    await checkState(page, shot, user);
    const file = path.join(OUT, `${shot.name}.png`);
    await page.screenshot({ path: file, type: 'png', fullPage: !!shot.fullPage });
    captured.push(shot.name);
    console.log('  ✓', shot.name);
  } catch (e) {
    skipped.push({ name: shot.name, reason: String(e.message ?? e).split('\n')[0] });
    console.log('  ✗', shot.name, '—', String(e.message ?? e).split('\n')[0]);
    // Best effort: still keep a plain screenshot of the page if the extra action failed.
    if (shot.action && shot.fallback !== false) {
      try {
        await page.keyboard.press('Escape');
        await page.screenshot({ path: path.join(OUT, `${shot.name}.png`), type: 'png' });
        console.log('    (saved page without the drawer)');
      } catch { /* ignore */ }
    }
  }
}

const clickButton = (name, opts = {}) => async (page) => {
  await page.getByRole('button', { name, exact: opts.exact ?? false }).first().click({ timeout: 10000 });
};
const clickTab = (name) => async (page) => {
  await page.getByRole('tab', { name }).first().click({ timeout: 10000 });
};
const waitDialog = async (page) => page.getByRole('dialog').first().waitFor({ state: 'visible', timeout: 10000 });

// ---------------------------------------------------------------- shot lists

const ownerShots = [
  { name: 'owner-dashboard', url: '/dashboard', wait: 1500 },
  { name: 'owner-approvals', url: '/approvals' },
  { name: 'owner-notifications', url: '/notifications' },
  { name: 'owner-company-profile', url: '/company' },
  { name: 'owner-branches', url: '/company/branches' },
  { name: 'owner-branch-drawer', url: '/company/branches', action: async (p) => { await clickButton('New branch')(p); await waitDialog(p); } },
  { name: 'owner-departments', url: '/company/departments' },
  {
    name: 'owner-locations-map', url: '/company/map', wait: 2500,
    action: async (p) => {
      await p.waitForFunction(() => [...document.querySelectorAll('img.leaflet-tile')].some((i) => i.complete && i.naturalWidth > 0), null, { timeout: 15000 }).catch(() => {});
    },
    afterWait: 1500,
  },
  { name: 'owner-users', url: '/access/users' },
  { name: 'owner-invite-user-drawer', url: '/access/users', action: async (p) => { await clickButton('Invite user')(p); await waitDialog(p); } },
  { name: 'owner-roles', url: '/access/roles' },
  {
    name: 'owner-role-editor-accountant', url: '/access/roles',
    action: async (p) => {
      const card = p.locator('h2, h3').filter({ hasText: /^Accountant$/ }).first()
        .locator('xpath=ancestor::*[.//button[normalize-space()="Edit"]][1]');
      await card.getByRole('button', { name: 'Edit' }).first().click({ timeout: 10000 });
      await waitDialog(p);
    },
    afterWait: 1200,
  },
  { name: 'owner-audit-log', url: '/audit' },
  {
    name: 'owner-audit-entry-drawer', url: '/audit',
    action: async (p) => {
      await p.locator('tbody tr').nth(2).click({ timeout: 10000 });
      await waitDialog(p);
    },
  },
  // HR
  { name: 'owner-hr-employees', url: '/hr/employees' },
  { name: 'owner-hr-employee-profile', url: '/hr/employees/2' },
  { name: 'owner-hr-employee-new', url: '/hr/employees/new' },
  { name: 'owner-hr-org-chart', url: '/hr/org-chart', wait: 1500 },
  { name: 'owner-hr-attendance', url: '/hr/attendance' },
  { name: 'owner-hr-leave-approvals', url: '/hr/leave?tab=approvals', wait: 2500 },
  { name: 'owner-hr-leave-balances', url: '/hr/leave?tab=balances', wait: 2500 },
  { name: 'owner-hr-shifts', url: '/hr/shifts' },
  { name: 'owner-hr-performance', url: '/hr/performance' },
  { name: 'owner-hr-reports', url: '/hr/reports', wait: 1500 },
  // Payroll
  { name: 'owner-payroll-runs', url: '/payroll/runs' },
  { name: 'owner-payroll-run-detail', url: '/payroll/runs/3' },
  { name: 'owner-payroll-run-paid', url: '/payroll/runs/1' },
  { name: 'owner-payroll-components', url: '/payroll/structures' },
  { name: 'owner-payroll-structures', url: '/payroll/structures', action: clickTab('Salary structures') },
  { name: 'owner-payroll-tax-tables', url: '/payroll/structures?tab=tax-tables', wait: 2500 },
  // Finance
  { name: 'owner-finance-accounts', url: '/finance/accounts' },
  { name: 'owner-finance-journals', url: '/finance/journals' },
  { name: 'owner-finance-journal-detail', url: '/finance/journals/58' },
  {
    name: 'owner-finance-journal-new', url: '/finance/journals/new', wait: 1500,
    action: async (p) => {
      const pick = async (label, code) => {
        const sel = p.getByLabel(label, { exact: true });
        await sel.waitFor({ timeout: 10000 });
        const value = await sel.evaluate((el, c) => [...el.options].find((o) => o.textContent.includes(c))?.value, code);
        if (!value) throw new Error(`No account option containing ${code}`);
        await sel.selectOption(value);
      };
      const memo = p.getByLabel(/memo/i).first();
      if (await memo.count()) await memo.fill('Office rent October 2026 (example — not saved)');
      await pick('Line 1 account', '6100');
      await p.getByLabel('Line 1 description', { exact: true }).fill('Office rent — October');
      await p.getByLabel('Line 1 debit', { exact: true }).fill('2500');
      await pick('Line 2 account', '1010');
      await p.getByLabel('Line 2 description', { exact: true }).fill('Paid from operating bank');
      await p.getByLabel('Line 2 credit', { exact: true }).fill('2500');
      await p.getByLabel('Line 2 credit', { exact: true }).press('Tab');
    },
  },
  { name: 'owner-finance-reports-page', url: '/finance/reports', expectError: true },
  { name: 'owner-finance-bank', url: '/finance/bank' },
  { name: 'owner-finance-periods', url: '/finance/periods' },
  { name: 'owner-finance-invoices', url: '/finance/invoices' },
  { name: 'owner-finance-invoice-pending', url: '/finance/invoices/15' },
  { name: 'owner-finance-invoice-overdue', url: '/finance/invoices/11' },
  { name: 'owner-finance-record-receipt-drawer', url: '/finance/invoices/11', action: async (p) => { await clickButton('Record receipt')(p); await waitDialog(p); } },
  { name: 'owner-finance-receipts', url: '/finance/receipts' },
  { name: 'owner-finance-bills', url: '/finance/bills' },
  { name: 'owner-finance-bill-detail', url: '/finance/bills/1' },
  { name: 'owner-finance-expenses', url: '/finance/expenses' },
  { name: 'owner-finance-expense-detail', url: '/finance/expenses/2' },
  { name: 'owner-finance-aging', url: '/finance/aging', wait: 1500 },
  // Sales
  { name: 'owner-sales-customers', url: '/sales/customers' },
  { name: 'owner-sales-customer-detail', url: '/sales/customers/1' },
  { name: 'owner-sales-leads', url: '/sales/leads' },
  { name: 'owner-sales-pipeline', url: '/sales/pipeline', wait: 1500 },
  { name: 'owner-sales-quotations', url: '/sales/quotations' },
  { name: 'owner-sales-quotation-detail', url: '/sales/quotations/1' },
  { name: 'owner-sales-orders', url: '/sales/orders' },
  { name: 'owner-sales-order-detail', url: '/sales/orders/14' },
  { name: 'owner-sales-order-approve', url: '/sales/orders/16' },
  { name: 'owner-sales-reports', url: '/sales/reports', wait: 1500 },
  // Procurement
  { name: 'owner-proc-suppliers', url: '/procurement/suppliers' },
  { name: 'owner-proc-supplier-detail', url: '/procurement/suppliers/1' },
  { name: 'owner-proc-requisitions', url: '/procurement/requisitions' },
  { name: 'owner-proc-requisition-detail', url: '/procurement/requisitions/5' },
  { name: 'owner-proc-orders', url: '/procurement/orders' },
  { name: 'owner-proc-order-detail', url: '/procurement/orders/1' },
  { name: 'owner-proc-order-submitted', url: '/procurement/orders/9' },
  { name: 'owner-proc-receipts', url: '/procurement/receipts' },
  { name: 'owner-proc-receipt-detail', url: '/procurement/receipts/1' },
  { name: 'owner-proc-reports', url: '/procurement/reports', wait: 1500 },
  // Inventory
  { name: 'owner-inv-products', url: '/inventory/products' },
  { name: 'owner-inv-product-detail', url: '/inventory/products/1' },
  { name: 'owner-inv-stock', url: '/inventory/stock' },
  { name: 'owner-inv-movements', url: '/inventory/movements' },
  { name: 'owner-inv-transfers', url: '/inventory/transfers' },
  { name: 'owner-inv-transfer-detail', url: '/inventory/transfers/1' },
  { name: 'owner-inv-adjustments', url: '/inventory/adjustments' },
  { name: 'owner-inv-warehouses', url: '/inventory/warehouses' },
  { name: 'owner-inv-reports', url: '/inventory/reports', wait: 1500 },
  // Projects
  { name: 'owner-projects', url: '/projects' },
  { name: 'owner-project-overview', url: '/projects/1' },
  { name: 'owner-project-board', url: '/projects/1', action: clickTab('Board'), afterWait: 1200 },
  {
    name: 'owner-project-task-drawer', url: '/projects/1',
    action: async (p) => {
      await clickTab('Board')(p);
      await p.waitForTimeout(1000);
      await p.getByText('Order conveyor extension', { exact: false }).first().click({ timeout: 10000 });
      await waitDialog(p);
    },
    afterWait: 1200,
  },
  // Documents, reports, search, settings
  { name: 'owner-documents', url: '/documents' },
  { name: 'owner-reports', url: '/reports' },
  ...[
    ['owner-report-pl', /^Profit and loss/],
    ['owner-report-bs', /^Balance sheet/],
    ['owner-report-tb', /^Trial balance/],
  ].map(([name, re]) => ({
    name, url: '/reports', afterWait: 1500,
    action: async (p) => {
      await p.getByRole('button', { name: re }).first().click({ timeout: 10000 });
      await p.waitForTimeout(600);
      await p.getByRole('button', { name: 'Run report' }).first().click({ timeout: 10000 });
      await p.waitForLoadState('networkidle').catch(() => {});
      await p.waitForTimeout(800);
      // Scroll the preview into view.
      await p.evaluate(() => window.scrollTo(0, 260));
    },
  })),
  { name: 'owner-search', url: '/search?q=office' },
  { name: 'owner-settings-profile', url: '/settings/profile' },
  { name: 'owner-settings-security', url: '/settings/security' },
  { name: 'owner-settings-notifications', url: '/settings/notifications' },
  { name: 'owner-settings-appearance', url: '/settings/appearance' },
  {
    name: 'owner-user-menu', url: '/dashboard', wait: 1200,
    action: async (p) => { await p.getByRole('button', { name: 'Account menu' }).click({ timeout: 10000 }); },
  },
];

const employeeShots = [
  { name: 'employee-dashboard', url: '/dashboard', wait: 1500 },
  { name: 'employee-leave', url: '/hr/leave' },
  { name: 'employee-attendance', url: '/hr/attendance' },
  { name: 'employee-payslips', url: '/payroll/my-payslips' },
  { name: 'employee-expenses', url: '/finance/expenses' },
  { name: 'employee-projects-mytasks', url: '/projects?view=mine', wait: 2500 },
  { name: 'employee-forbidden-invoices', url: '/finance/invoices', expectError: true },
  { name: 'employee-forbidden-invoice-detail', url: '/finance/invoices/11', expectError: true },
];

const platformShots = [
  { name: 'platform-landing', url: '/dashboard', expectError: true },
  { name: 'platform-companies', url: '/platform/companies' },
  { name: 'platform-new-company-drawer', url: '/platform/companies', action: async (p) => { await clickButton('New company')(p); await waitDialog(p); } },
  { name: 'platform-users', url: '/platform/users' },
  { name: 'platform-system', url: '/platform/system' },
];

// ---------------------------------------------------------------- run

const browser = await chromium.launch({ executablePath: EDGE });
const want = (prefix) => !FILTER || FILTER.startsWith(prefix) || prefix.startsWith(FILTER.split('-')[0]);

try {
  // Logged-out pages
  if (want('public')) {
    console.log('public (logged out)');
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 });
    await context.addInitScript(HIDE_DEV);
  const page = await context.newPage();
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await shoot(page, { name: 'public-login', url: '/login' }, '(logged out)');
    await shoot(page, {
      name: 'public-login-filled', url: '/login',
      action: async (p) => { await p.fill('#email', 'owner@northwind.test'); await p.fill('#password', 'password'); },
    }, '(logged out)');
    await shoot(page, { name: 'public-forgot-password', url: '/forgot-password' }, '(logged out)');
    await shoot(page, { name: 'public-dark-login', url: '/login', action: async (p) => { await p.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark')); } }, '(logged out)');
    await context.close();
  }

  if (want('owner')) {
    console.log('owner@northwind.test');
    const { context, page } = await login(browser, 'owner@northwind.test');
    for (const s of ownerShots) await shoot(page, s, 'owner@northwind.test');

    // Dark mode: toggled through the account menu (a harmless, reversible UI preference),
    // captured, then switched back to light.
    if (!FILTER || 'owner-dark'.includes(FILTER) || FILTER.startsWith('owner-dark')) {
      const toggle = async (mode) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await settle(page);
        await page.getByRole('button', { name: 'Account menu' }).click();
        await page.getByRole('radio', { name: mode }).click();
        await page.keyboard.press('Escape');
        await page.waitForTimeout(600);
      };
      try {
        await toggle('Dark');
        for (const s of [
          { name: 'owner-dark-dashboard', url: '/dashboard', wait: 1500 },
          { name: 'owner-dark-invoices', url: '/finance/invoices' },
          { name: 'owner-dark-appearance', url: '/settings/appearance' },
        ]) await shoot(page, s, 'owner@northwind.test');
      } finally {
        await toggle('Light').catch(() => {});
      }
    }
    await context.close();
  }

  if (want('employee')) {
    console.log('employee@northwind.test');
    const { context, page } = await login(browser, 'employee@northwind.test');
    for (const s of employeeShots) await shoot(page, s, 'employee@northwind.test');
    await context.close();
  }

  if (want('platform')) {
    console.log('platform@erp.test');
    const { context, page } = await login(browser, 'platform@erp.test');
    for (const s of platformShots) await shoot(page, s, 'platform@erp.test');
    await context.close();
  }

  if (want('mobile')) {
    console.log('mobile (390×844) owner@northwind.test');
    const { context, page } = await login(browser, 'owner@northwind.test', { width: 390, height: 844 });
    await shoot(page, { name: 'mobile-dashboard', url: '/dashboard', wait: 1500 }, 'owner@northwind.test (mobile)');
    await shoot(page, {
      name: 'mobile-menu-open', url: '/dashboard', wait: 1200,
      action: async (p) => { await p.getByRole('button', { name: 'Open menu' }).click({ timeout: 10000 }); },
    }, 'owner@northwind.test (mobile)');
    await shoot(page, { name: 'mobile-invoices', url: '/finance/invoices' }, 'owner@northwind.test (mobile)');
    await shoot(page, { name: 'mobile-approvals', url: '/approvals' }, 'owner@northwind.test (mobile)');
    await context.close();
  }
} finally {
  await browser.close();
}

const prev = fs.existsSync(path.join(OUT, '_issues.json')) && FILTER ? JSON.parse(fs.readFileSync(path.join(OUT, '_issues.json'), 'utf8')) : [];
const merged = [...prev.filter((i) => !captured.includes(i.shot)), ...issues];
fs.writeFileSync(path.join(OUT, '_issues.json'), JSON.stringify(merged, null, 2));
console.log(`\nCaptured ${captured.length} screenshot(s); skipped ${skipped.length}.`);
if (skipped.length) console.log('Skipped:', skipped);
if (issues.length) { console.log('Pages with error/empty states:'); for (const i of issues) console.log(' -', i); }
