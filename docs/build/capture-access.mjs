// Screenshots for the "Admin hierarchy & access" guide.
//
//   node capture-access.mjs
//
// Walks the real UI as each level of the hierarchy and highlights the element
// about to be clicked with a red outline. Unlike capture.mjs this script DOES
// create clearly fictional demo data in the development database:
//   - company "Guide Demo Co (Demo)" (is_demo) with owner guide.owner@guidedemo.test
//   - Northwind users sub.admin@, branch.hr@, branch.accountant@, branch.manager@,
//     branch.sales@northwind.test
//   - Northwind role "Branch Sales Supervisor (Demo)"
// Toggles made only for screenshots (deactivation) are reverted at the end.
// Re-running is safe: when the data already exists, forms are filled and
// photographed, then cancelled instead of submitted.
// Development only — refuses to run against anything but localhost.

import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(HERE, 'screenshots-access');
const BACKEND = path.resolve(HERE, '..', '..', 'backend');
const BASE = process.env.ERP_URL ?? 'http://localhost:3000';
const DEMO_PW = 'password';
const OWNER_NEW_PW = 'GuideDemo-Owner-2026!x';
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PHP = fs.existsSync(`${process.env.LOCALAPPDATA}/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe`)
  ? `${process.env.LOCALAPPDATA}/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe`
  : 'php';

if (!/^https?:\/\/(localhost|127\.0\.0\.1)/.test(BASE)) throw new Error('capture-access.mjs creates demo data and only runs against localhost.');
fs.mkdirSync(OUT, { recursive: true });

const facts = { created: [], restored: [], issues: [], notes: [] };
const shots = [];

// ---------------------------------------------------------------- backend helpers
function tinker(code) {
  return execFileSync(PHP, ['artisan', 'tinker', '--execute', code], { cwd: BACKEND, encoding: 'utf8' }).trim();
}
const userExists = (email) => tinker(`echo App\\Models\\User::where('email','${email}')->exists() ? 'yes' : 'no';`).endsWith('yes');
const companyExists = (name) => tinker(`echo App\\Models\\Company::where('name','${name}')->exists() ? 'yes' : 'no';`).endsWith('yes');
const roleExists = (name) => tinker(`echo App\\Models\\Role::withoutGlobalScopes()->where('name','${name}')->exists() ? 'yes' : 'no';`).endsWith('yes');
/** Development shortcut: real users set their password from the e-mailed link. */
function setDemoPassword(email) {
  tinker(`$u = App\\Models\\User::where('email','${email}')->first(); $u->forceFill(['password'=>'${DEMO_PW}','email_verified_at'=>now()])->save(); echo 'ok';`);
}

function findResetLink(email) {
  const log = path.join(BACKEND, 'storage', 'logs', 'laravel.log');
  if (!fs.existsSync(log)) return null;
  const text = fs.readFileSync(log, 'utf8');
  const enc = encodeURIComponent(email);
  const re = /https?:\/\/[^\s"'<>]*reset-password[^\s"'<>]*/g;
  const all = [...text.matchAll(re)].map((m) => m[0].replace(/&amp;/g, '&')).filter((u) => u.includes(enc) || u.includes(email));
  if (!all.length) return null;
  const link = all[all.length - 1];
  // Context lines for the guide (token redacted).
  const idx = text.lastIndexOf(link.replace(/&/g, '&amp;')) >= 0 ? text.lastIndexOf(link.replace(/&/g, '&amp;')) : text.lastIndexOf(link);
  const start = text.lastIndexOf('\n[', idx);
  const excerpt = text.slice(start + 1, idx + link.length + 200).split('\n')
    .filter((l) => /Subject|To:|reset-password|Reset Password|password|\[\d{4}-/.test(l)).slice(0, 12).join('\n');
  return { link, excerpt: excerpt.replace(/token=[^&\s"]+/g, 'token=<redacted>').replace(/reset-password\/[A-Za-z0-9]{20,}/g, 'reset-password/<redacted>') };
}

// ---------------------------------------------------------------- browser helpers
const browser = await chromium.launch({ executablePath: EDGE });
const contexts = new Map();

const HIDE_DEV = () => {
  const add = () => { const s = document.createElement('style'); s.textContent = 'nextjs-portal{display:none!important}'; (document.head || document.documentElement).appendChild(s); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add); else add();
};

async function newPage(viewport = { width: 1440, height: 900 }) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 2, locale: 'en-US' });
  await context.addInitScript(HIDE_DEV);
  const page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });
  return { context, page };
}

async function settle(page, extra = 900) {
  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  for (let i = 0; i < 40; i++) {
    const n = await page.evaluate(() => [...document.querySelectorAll('[class*="skeleton" i]')].filter((e) => { const r = e.getBoundingClientRect(); return r.width && r.height; }).length).catch(() => 0);
    if (!n) break;
    await page.waitForTimeout(500);
  }
  await page.waitForTimeout(extra);
}

async function login(email, password = DEMO_PW, viewport) {
  const key = `${email}@${viewport?.height ?? 900}`;
  if (contexts.has(key)) return contexts.get(key);
  const { context, page } = await newPage(viewport);
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('#email', email);
  await page.fill('#password', password);
  await page.click('button[type="submit"]');
  for (let i = 0; i < 90 && /\/login/.test(page.url()); i++) await page.waitForTimeout(500);
  await settle(page);
  const entry = { context, page, email };
  contexts.set(key, entry);
  return entry;
}
async function closeLogin(email, height = 900) {
  const key = `${email}@${height}`;
  const e = contexts.get(key);
  if (e) { await e.context.close(); contexts.delete(key); }
}

async function go(page, url, extra) {
  await page.goto(BASE + url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await settle(page, extra);
}

/** Red outline (+ optional numbered badge) around an element; returns nothing. */
async function mark(page, locator, label = '') {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  const box = await locator.boundingBox();
  if (!box) throw new Error('mark: element not visible');
  await page.evaluate(({ box, label }) => {
    const pad = 5;
    const d = document.createElement('div');
    d.className = '__guide_mark';
    Object.assign(d.style, {
      position: 'fixed', left: `${box.x - pad}px`, top: `${box.y - pad}px`, width: `${box.width + pad * 2}px`, height: `${box.height + pad * 2}px`,
      border: '3px solid #ef4444', borderRadius: '8px', boxShadow: '0 0 0 4px rgba(239,68,68,.22)', zIndex: 2147483647, pointerEvents: 'none',
    });
    if (label) {
      const b = document.createElement('div');
      b.textContent = label;
      Object.assign(b.style, {
        position: 'absolute', top: '-14px', left: '-14px', minWidth: '26px', height: '26px', borderRadius: '13px', background: '#ef4444', color: '#fff',
        font: '700 14px/26px Inter, Segoe UI, sans-serif', textAlign: 'center', padding: '0 6px', boxShadow: '0 2px 6px rgba(0,0,0,.25)',
      });
      d.appendChild(b);
    }
    document.body.appendChild(d);
  }, { box, label });
}
const clearMarks = (page) => page.evaluate(() => document.querySelectorAll('.__guide_mark').forEach((e) => e.remove())).catch(() => {});

async function shot(page, name, { fullPage = false } = {}) {
  await page.waitForTimeout(250);
  const text = await page.evaluate(() => document.body.innerText).catch(() => '');
  for (const t of ['This page couldn’t load', 'Something went wrong']) {
    if (text.includes(t) && !name.includes('forbidden') && !name.includes('denied')) facts.issues.push({ shot: name, url: page.url().replace(BASE, ''), text: t });
  }
  await page.screenshot({ path: path.join(OUT, `${name}.png`), type: 'png', fullPage });
  await clearMarks(page);
  shots.push(name);
  console.log('  ✓', name);
}

const dialog = (page) => page.getByRole('dialog').last();
const waitDialog = (page) => dialog(page).waitFor({ state: 'visible', timeout: 15000 });
const btn = (scope, name, exact = true) => scope.getByRole('button', { name, exact }).first();
const checkboxByText = (scope, text) => scope.locator('label').filter({ has: scope.page().getByText(text, { exact: true }) }).first();

const reEsc = (t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
/** Field by its visible label; required fields carry a trailing '*'. */
const field = (scope, label) => scope.getByLabel(new RegExp(`^\\s*${reEsc(label)}\\s*\\*?\\s*$`)).first();
async function fillField(scope, label, value) {
  const f = field(scope, label);
  await f.fill(value);
  return f;
}

async function rowActions(page, text) {
  const row = page.locator('tbody tr').filter({ hasText: text }).first();
  await row.waitFor({ timeout: 15000 });
  return row.getByRole('button', { name: 'Row actions' });
}

// ONLY=8b,9 node capture-access.mjs  -> run selected steps (by leading number) only.
const ONLY = process.env.ONLY ? process.env.ONLY.split(',') : null;
async function step(title, fn) {
  if (ONLY && !ONLY.some((o) => title.startsWith(o + '.'))) return;
  console.log(title);
  try { await fn(); } catch (e) {
    const msg = String(e.message ?? e).split('\n')[0];
    console.log('  ✗', msg);
    facts.issues.push({ step: title, error: msg });
  }
}

// ---------------------------------------------------------------- data
const GUIDE_CO = 'Guide Demo Co (Demo)';
const GUIDE_OWNER = { name: 'Guide Owner (Demo)', email: 'guide.owner@guidedemo.test' };
const SUB_ADMIN = { name: 'Sub Admin (Guide Demo)', email: 'sub.admin@northwind.test' };
const BRANCH_USERS = [
  { name: 'Branch HR (Guide Demo)', email: 'branch.hr@northwind.test', role: 'HR Manager', branch: 'Boston Office' },
  { name: 'Branch Accountant (Guide Demo)', email: 'branch.accountant@northwind.test', role: 'Accountant', branch: 'Chicago Office' },
  { name: 'Branch Manager (Guide Demo)', email: 'branch.manager@northwind.test', role: 'Department Manager', branch: 'Austin Office' },
];
const CUSTOM_ROLE = 'Branch Sales Supervisor (Demo)';
const SALES_SUP = { name: 'Branch Sales Supervisor (Guide Demo)', email: 'branch.sales@northwind.test', branch: 'Boston Office' };

// ================================================================ 1. Platform owner
await step('1. platform owner: security page + companies', async () => {
  const { page } = await login('platform@erp.test');
  await go(page, '/settings/security');
  await mark(page, btn(page, 'Set up two-factor'), '1');
  await shot(page, 'a01-platform-security-2fa');

  await go(page, '/platform/companies');
  await mark(page, page.getByRole('link', { name: 'Companies' }).first(), '1');
  await mark(page, btn(page, 'New company'), '2');
  await shot(page, 'a02-platform-companies');

  await btn(page, 'New company').click();
  await waitDialog(page);
  const d = dialog(page);
  await mark(page, await fillField(d, 'Name', GUIDE_CO), '1');
  await fillField(d, 'Legal name', 'Guide Demo Company (Fictional) Ltd.');
  await fillField(d, 'Email', 'info@guidedemo.test');
  await fillField(d, 'Country', 'PK');
  await field(d, 'Base currency').selectOption('PKR').catch(() => {});
  await field(d, 'Time zone').selectOption('Asia/Karachi').catch(() => {});
  const demo = d.getByRole('switch', { name: 'Demo company' });
  if ((await demo.getAttribute('aria-checked')) !== 'true') await demo.click();
  await mark(page, demo, '2');
  await shot(page, 'a03-new-company-details');

  await field(d, 'Owner name').scrollIntoViewIfNeeded();
  await mark(page, await fillField(d, 'Owner name', GUIDE_OWNER.name), '3');
  await mark(page, await fillField(d, 'Owner email', GUIDE_OWNER.email), '4');
  await mark(page, btn(d, 'Create company'), '5');
  await shot(page, 'a04-new-company-owner');

  if (companyExists(GUIDE_CO)) {
    facts.notes.push(`${GUIDE_CO} already existed — form cancelled.`);
    await btn(d, 'Cancel').click();
  } else {
    await btn(d, 'Create company').click();
    await d.waitFor({ state: 'hidden', timeout: 30000 });
    facts.created.push(`Company "${GUIDE_CO}" (is_demo, PKR, Asia/Karachi) with owner ${GUIDE_OWNER.email} — via Platform → Companies → New company`);
  }
  await settle(page, 1500);
  const row = page.locator('tbody tr').filter({ hasText: GUIDE_CO }).first();
  await mark(page, row, '');
  await shot(page, 'a05-company-created');
});

await step('1b. platform users: platform admin menu (not granted)', async () => {
  const { page } = await login('platform@erp.test');
  await go(page, '/platform/users');
  await shot(page, 'a06-platform-users');
  const actions = await rowActions(page, 'sub.admin@northwind.test').catch(() => rowActions(page, 'admin@northwind.test'));
  await actions.click();
  const item = page.getByRole('menuitem', { name: /platform admin/i }).first();
  await item.waitFor({ timeout: 10000 });
  await mark(page, item, '1');
  await shot(page, 'a07-platform-make-admin-menu');
  await item.click();
  await page.getByRole('alertdialog').or(page.getByRole('dialog')).last().waitFor({ timeout: 10000 });
  await page.waitForTimeout(400);
  await shot(page, 'a08-platform-make-admin-confirm');
  await page.keyboard.press('Escape');
  const cancel = page.getByRole('button', { name: 'Cancel', exact: true });
  if (await cancel.count()) await cancel.last().click().catch(() => {});
  facts.notes.push('Platform-admin confirm dialog was cancelled — no platform role was granted.');
});

// ================================================================ 2. Owner receives link, sets password
await step('2. guide owner: reset link → set password → first login', async () => {
  let found = null;
  for (let i = 0; i < 10 && !found; i++) { found = findResetLink(GUIDE_OWNER.email); if (!found) await new Promise((r) => setTimeout(r, 1000)); }
  if (!found) {
    try { execFileSync(PHP, ['artisan', 'queue:work', '--stop-when-empty', '--tries=1'], { cwd: BACKEND, encoding: 'utf8', timeout: 120000 }); } catch { /* ignore */ }
    found = findResetLink(GUIDE_OWNER.email);
  }
  if (found) {
    facts.resetLogExcerpt = found.excerpt;
    const { context, page } = await newPage();
    const u = new URL(found.link);
    await page.goto(`${BASE}${u.pathname}${u.search}`, { waitUntil: 'networkidle' });
    await settle(page);
    await shot(page, 'a09-reset-password-page');
    await mark(page, page.locator('#password'), '1');
    await page.fill('#password', OWNER_NEW_PW);
    await mark(page, page.locator('#password_confirmation'), '2');
    await page.fill('#password_confirmation', OWNER_NEW_PW);
    await mark(page, page.locator('button[type="submit"]'), '3');
    await shot(page, 'a10-reset-password-filled');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    await shot(page, 'a11-reset-password-done');
    await context.close();
    facts.notes.push(`${GUIDE_OWNER.email} set a password through the real /reset-password link from laravel.log.`);
  } else {
    facts.issues.push({ step: 'reset link', error: 'No reset-password link for the new owner found in storage/logs/laravel.log; password set via tinker instead.' });
    setDemoPassword(GUIDE_OWNER.email);
  }
  const pw = found ? OWNER_NEW_PW : DEMO_PW;
  const { page } = await login(GUIDE_OWNER.email, pw);
  if (/\/login/.test(page.url())) { setDemoPassword(GUIDE_OWNER.email); await closeLogin(GUIDE_OWNER.email); }
  const { page: p2 } = await login(GUIDE_OWNER.email, /\/login/.test(page.url()) ? DEMO_PW : pw);
  await go(p2, '/dashboard', 1500);
  await shot(p2, 'a12-guide-owner-dashboard');
  await go(p2, '/access/roles');
  await shot(p2, 'a13-guide-owner-roles');
});

// ================================================================ 3. Company owner → company admin
await step('3. owner@northwind invites sub admin', async () => {
  const { page } = await login('owner@northwind.test');
  await go(page, '/access/users');
  await mark(page, page.getByRole('link', { name: 'Users', exact: true }).first(), '1');
  await mark(page, btn(page, 'Invite user'), '2');
  await shot(page, 'a14-owner-users-invite-button');
  await btn(page, 'Invite user').click();
  await waitDialog(page);
  const d = dialog(page);
  await mark(page, await fillField(d, 'Full name', SUB_ADMIN.name), '1');
  await mark(page, await fillField(d, 'Work email', SUB_ADMIN.email), '2');
  const role = checkboxByText(d, 'Company Administrator');
  await role.click();
  await mark(page, role, '3');
  await mark(page, d.getByRole('switch', { name: 'Access to all branches' }), '4');
  await mark(page, btn(d, 'Send invitation'), '5');
  await shot(page, 'a15-invite-sub-admin');
  if (userExists(SUB_ADMIN.email)) { await btn(d, 'Cancel').click(); facts.notes.push(`${SUB_ADMIN.email} already existed — invite cancelled.`); }
  else {
    await btn(d, 'Send invitation').click();
    await d.waitFor({ state: 'hidden', timeout: 30000 });
    facts.created.push(`User ${SUB_ADMIN.email} — Company Administrator in Northwind, all branches (invited by owner@northwind.test)`);
  }
  await settle(page, 1200);
  await mark(page, page.locator('tbody tr').filter({ hasText: SUB_ADMIN.email }).first());
  await shot(page, 'a16-sub-admin-in-list');
  setDemoPassword(SUB_ADMIN.email);
});

// ================================================================ 4. Company admin → branch managers
await step('4. sub admin invites branch-restricted managers', async () => {
  const { page } = await login(SUB_ADMIN.email);
  await go(page, '/dashboard', 1200);
  await shot(page, 'a17-sub-admin-dashboard');
  for (const [i, u] of BRANCH_USERS.entries()) {
    await go(page, '/access/users');
    await btn(page, 'Invite user').click();
    await waitDialog(page);
    const d = dialog(page);
    await fillField(d, 'Full name', u.name);
    await fillField(d, 'Work email', u.email);
    const role = checkboxByText(d, u.role);
    await role.click();
    const sw = d.getByRole('switch', { name: 'Access to all branches' });
    if ((await sw.getAttribute('aria-checked')) === 'true') await sw.click();
    const branch = d.locator('label').filter({ hasText: u.branch }).first();
    await branch.waitFor({ timeout: 10000 });
    await branch.click();
    if (i === 0) {
      await mark(page, role, '1');
      await mark(page, sw, '2');
      await mark(page, branch, '3');
      await mark(page, btn(d, 'Send invitation'), '4');
      await d.getByText('Access to all branches').scrollIntoViewIfNeeded();
      await clearMarks(page);
      await mark(page, sw, '2');
      await mark(page, branch, '3');
      await mark(page, btn(d, 'Send invitation'), '4');
      await shot(page, 'a18-invite-branch-hr-restricted');
    }
    if (userExists(u.email)) { await btn(d, 'Cancel').click(); facts.notes.push(`${u.email} already existed — invite cancelled.`); }
    else {
      await btn(d, 'Send invitation').click();
      await d.waitFor({ state: 'hidden', timeout: 30000 });
      facts.created.push(`User ${u.email} — ${u.role} in Northwind, restricted to ${u.branch} (invited by ${SUB_ADMIN.email})`);
    }
    setDemoPassword(u.email);
  }
  await go(page, '/access/users', 1200);
  for (const u of BRANCH_USERS) await mark(page, page.locator('tbody tr').filter({ hasText: u.email }).first());
  await shot(page, 'a19-users-with-branches');
});

// ================================================================ 5. Custom role
await step('5. sub admin creates custom role and assigns it', async () => {
  const { page } = await login(SUB_ADMIN.email);
  await go(page, '/access/roles');
  await mark(page, btn(page, 'New role'), '1');
  await shot(page, 'a20-roles-new-role-button');
  await btn(page, 'New role').click();
  await waitDialog(page);
  const d = dialog(page);
  await mark(page, await fillField(d, 'Name', CUSTOM_ROLE), '1');
  await field(d, 'Description').fill('Fictional demo role: customers, quotations and sales orders for one branch.');
  await shot(page, 'a21-new-role-name');
  const wanted = ['View executive dashboard', 'View customers', 'View quotations', 'Manage quotations', 'View sales orders', 'View reports'];
  const search = d.getByLabel('Filter permissions');
  for (const text of wanted) {
    await search.fill('');
    const cb = checkboxByText(d, text);
    await cb.scrollIntoViewIfNeeded();
    const input = cb.locator('input[type="checkbox"]');
    if (!(await input.isChecked())) await cb.click();
  }
  await search.fill('quotation');
  await page.waitForTimeout(500);
  await mark(page, search, '2');
  await mark(page, checkboxByText(d, 'View quotations'), '3');
  await mark(page, checkboxByText(d, 'Manage quotations'), '3');
  await shot(page, 'a22-new-role-filter-quotations');
  await search.fill('');
  await page.waitForTimeout(400);
  const sales = d.getByText('Sales', { exact: true }).first();
  await sales.scrollIntoViewIfNeeded().catch(() => {});
  await page.waitForTimeout(300);
  await mark(page, btn(d, 'Create role'), '4');
  await shot(page, 'a23-new-role-sales-section');
  if (roleExists(CUSTOM_ROLE)) { await btn(d, 'Cancel').click(); facts.notes.push(`${CUSTOM_ROLE} already existed — form cancelled.`); }
  else {
    await btn(d, 'Create role').click();
    await d.waitFor({ state: 'hidden', timeout: 30000 });
    facts.created.push(`Role "${CUSTOM_ROLE}" in Northwind: ${wanted.join(', ')}`);
  }
  await settle(page, 1000);
  const card = page.locator('h2, h3').filter({ hasText: CUSTOM_ROLE }).first().locator('xpath=ancestor::*[.//button][1]');
  await mark(page, card);
  await shot(page, 'a24-custom-role-card');

  // System role (Accountant) — editable permissions, fixed name.
  const acc = page.locator('h2, h3').filter({ hasText: /^Accountant$/ }).first().locator('xpath=ancestor::*[.//button[normalize-space()="Edit"]][1]');
  await acc.getByRole('button', { name: 'Edit' }).click();
  await waitDialog(page);
  await shot(page, 'a25-system-role-accountant');
  await btn(dialog(page), 'Cancel').click();
  await page.waitForTimeout(500);
  // Company Owner — locked.
  const own = page.locator('h2, h3').filter({ hasText: /^Company Owner$/ }).first().locator('xpath=ancestor::*[.//button][1]');
  await own.getByRole('button', { name: /View|Edit/ }).first().click();
  await waitDialog(page);
  await page.waitForTimeout(400);
  await mark(page, dialog(page).getByText('The Company Owner role always has every permission and cannot be edited.'));
  await shot(page, 'a26-owner-role-locked');
  await page.keyboard.press('Escape');

  // Assign the custom role by inviting the supervisor.
  await go(page, '/access/users');
  await btn(page, 'Invite user').click();
  await waitDialog(page);
  const d2 = dialog(page);
  await fillField(d2, 'Full name', SALES_SUP.name);
  await fillField(d2, 'Work email', SALES_SUP.email);
  const r = checkboxByText(d2, CUSTOM_ROLE);
  await r.scrollIntoViewIfNeeded();
  await r.click();
  const sw = d2.getByRole('switch', { name: 'Access to all branches' });
  if ((await sw.getAttribute('aria-checked')) === 'true') await sw.click();
  const b = d2.locator('label').filter({ hasText: SALES_SUP.branch }).first();
  await b.click();
  await r.scrollIntoViewIfNeeded();
  await mark(page, r, '1');
  await shot(page, 'a27-assign-custom-role');
  if (userExists(SALES_SUP.email)) { await btn(d2, 'Cancel').click(); }
  else {
    await btn(d2, 'Send invitation').click();
    await d2.waitFor({ state: 'hidden', timeout: 30000 });
    facts.created.push(`User ${SALES_SUP.email} — ${CUSTOM_ROLE}, restricted to ${SALES_SUP.branch}`);
  }
  setDemoPassword(SALES_SUP.email);
});

// ================================================================ 6. What each role sees
const GALLERY = [
  ['owner', 'Company Owner'], ['admin', 'Company Administrator'], ['hr', 'HR Manager'], ['accountant', 'Accountant'],
  ['sales', 'Sales Manager'], ['inventory', 'Inventory Manager'], ['procurement', 'Procurement Officer'],
  ['manager', 'Department Manager'], ['employee', 'Employee'], ['auditor', 'Read-Only Auditor'],
];
await step('6. per-role gallery', async () => {
  const tall = { width: 1440, height: 1240 };
  for (const [u] of GALLERY) {
    const email = `${u}@northwind.test`;
    const { page } = await login(email, DEMO_PW, tall);
    await go(page, '/dashboard', 1500);
    await shot(page, `g-${u}`);
    await closeLogin(email, tall.height);
  }
  const { page } = await login(SALES_SUP.email, DEMO_PW, tall);
  await go(page, '/dashboard', 1500);
  await shot(page, 'g-custom-sales-supervisor');
  await go(page, '/sales/quotations', 1200);
  await shot(page, 'g-custom-sales-supervisor-quotations');
  await closeLogin(SALES_SUP.email, tall.height);
});

// ================================================================ 7. Branch limit + forbidden
await step('7. branch-restricted HR + forbidden screen', async () => {
  const { page } = await login(BRANCH_USERS[0].email);
  await go(page, '/hr/employees', 1200);
  await shot(page, 'a28-branch-hr-employees');
  await go(page, '/finance/invoices', 1200);
  await shot(page, 'a29-branch-hr-forbidden');
  await go(page, '/access/users', 1200);
  await shot(page, 'a30-branch-hr-forbidden-users');
});

// ================================================================ 8. Change / remove access
await step('8. owner changes and removes access', async () => {
  const { page } = await login('owner@northwind.test');
  // Change role: Department Manager → + Employee
  await go(page, '/access/users');
  const act = await rowActions(page, BRANCH_USERS[2].email);
  await act.click();
  const edit = page.getByRole('menuitem', { name: 'Edit access' });
  await edit.waitFor();
  await mark(page, act, '1');
  await mark(page, edit, '2');
  await shot(page, 'a31-row-actions-menu');
  await edit.click();
  await waitDialog(page);
  let d = dialog(page);
  const emp = checkboxByText(d, 'Employee');
  if (!(await emp.locator('input').isChecked())) await emp.click();
  await mark(page, emp, '3');
  await mark(page, btn(d, 'Save access'), '4');
  await shot(page, 'a32-edit-access-change-role');
  await btn(d, 'Save access').click();
  await d.waitFor({ state: 'hidden', timeout: 30000 });
  facts.created.push(`${BRANCH_USERS[2].email}: added role "Employee" (role change demo)`);

  // Deactivate membership of the sales supervisor, show effect, then restore.
  await go(page, '/access/users');
  await (await rowActions(page, SALES_SUP.email)).click();
  await page.getByRole('menuitem', { name: 'Edit access' }).click();
  await waitDialog(page);
  d = dialog(page);
  const active = d.getByRole('switch', { name: 'Active in this company' });
  await active.scrollIntoViewIfNeeded();
  if ((await active.getAttribute('aria-checked')) === 'true') await active.click();
  await mark(page, active, '1');
  await mark(page, btn(d, 'Save access'), '2');
  await shot(page, 'a33-deactivate-membership');
  await btn(d, 'Save access').click();
  await d.waitFor({ state: 'hidden', timeout: 30000 });
  await settle(page, 1000);
  await mark(page, page.locator('tbody tr').filter({ hasText: SALES_SUP.email }).first());
  await shot(page, 'a34-membership-inactive-row');
  {
    const { context, page: p } = await newPage();
    await p.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
    await p.fill('#email', SALES_SUP.email); await p.fill('#password', DEMO_PW);
    await p.click('button[type="submit"]');
    await p.waitForTimeout(5000);
    await settle(p, 1000);
    await shot(p, 'a35-inactive-member-login');
    await context.close();
  }
  // restore
  await go(page, '/access/users');
  await (await rowActions(page, SALES_SUP.email)).click();
  await page.getByRole('menuitem', { name: 'Edit access' }).click();
  await waitDialog(page);
  d = dialog(page);
  const a2 = d.getByRole('switch', { name: 'Active in this company' });
  if ((await a2.getAttribute('aria-checked')) !== 'true') await a2.click();
  await btn(d, 'Save access').click();
  await d.waitFor({ state: 'hidden', timeout: 30000 });
  facts.restored.push(`${SALES_SUP.email}: membership deactivated for screenshots, then re-activated`);

  // Remove from company (the branch accountant).
  await go(page, '/access/users');
  const ra = await rowActions(page, BRANCH_USERS[1].email);
  await ra.click();
  const rm = page.getByRole('menuitem', { name: 'Remove from company' });
  await rm.waitFor();
  await mark(page, rm, '1');
  await shot(page, 'a36-remove-menu');
  await rm.click();
  await page.waitForTimeout(600);
  await shot(page, 'a37-remove-confirm');
  await page.getByRole('button', { name: 'Remove', exact: true }).last().click();
  await page.waitForTimeout(1500);
  facts.created.push(`${BRANCH_USERS[1].email}: removed from Northwind (remove-from-company demo; the account itself remains)`);
});

await step('8b. platform-level deactivate + reactivate', async () => {
  const { page } = await login('platform@erp.test');
  await go(page, '/platform/users');
  const a = await rowActions(page, SALES_SUP.email);
  await a.click();
  const item = page.getByRole('menuitem', { name: 'Deactivate account' });
  await item.waitFor();
  await mark(page, item, '1');
  await shot(page, 'a38-platform-deactivate-menu');
  await item.click();
  await page.waitForTimeout(600);
  await shot(page, 'a39-platform-deactivate-confirm');
  await page.getByRole('button', { name: 'Confirm', exact: true }).last().click();
  await settle(page, 1200);
  await mark(page, page.locator('tbody tr').filter({ hasText: SALES_SUP.email }).first());
  await shot(page, 'a40-platform-deactivated-row');
  await (await rowActions(page, SALES_SUP.email)).click();
  await page.getByRole('menuitem', { name: 'Reactivate account' }).click();
  await settle(page, 1000);
  facts.restored.push(`${SALES_SUP.email}: account deactivated at platform level for screenshots, then reactivated`);
});

// ================================================================ 9. Audit log
await step('9. audit log entries', async () => {
  const { page } = await login('owner@northwind.test');
  await go(page, '/audit');
  const cat = page.getByLabel(/categor/i).first();
  if (await cat.count()) { await cat.selectOption({ label: 'Users' }).catch(() => {}); await settle(page, 800); }
  await shot(page, 'a41-audit-users');
  const row = page.locator('tbody tr').filter({ hasText: /users\.(invited|membership)/ }).first();
  await row.click();
  await waitDialog(page);
  await page.waitForTimeout(500);
  await shot(page, 'a42-audit-entry-drawer');
  await page.keyboard.press('Escape');
  if (await cat.count()) { await cat.selectOption({ label: 'Roles & permissions' }).catch(() => {}); await settle(page, 800); }
  await shot(page, 'a43-audit-roles');
  const r2 = page.locator('tbody tr').first();
  await r2.click();
  await waitDialog(page);
  await page.waitForTimeout(500);
  await shot(page, 'a44-audit-role-entry');
});

// ================================================================ 10. Maker–checker
await step('10. maker-checker (manager cannot approve own requisition)', async () => {
  const { page } = await login('manager@northwind.test');
  await go(page, '/approvals', 1200);
  await mark(page, page.getByText('Your own requests never appear here.').first());
  await shot(page, 'a45-manager-approvals');
  await go(page, '/procurement/requisitions/5', 1200);
  await shot(page, 'a46-manager-own-requisition');
  const { page: op } = await login('owner@northwind.test');
  await go(op, '/procurement/requisitions/5', 1200);
  await mark(op, btn(op, 'Approve'));
  await shot(op, 'a47-owner-can-approve-requisition');
});

await browser.close();
const factsPath = path.join(OUT, '_facts.json');
if (ONLY && fs.existsSync(factsPath)) {
  // Partial run: keep what the full run recorded and append this run's facts.
  const prev = JSON.parse(fs.readFileSync(factsPath, 'utf8'));
  for (const k of ['created', 'restored', 'notes']) facts[k] = [...(prev[k] ?? []), ...facts[k]];
  facts.issues = [...(prev.issues ?? []).filter((i) => !ONLY.some((o) => String(i.step ?? '').startsWith(o + '.'))), ...facts.issues];
  facts.resetLogExcerpt = facts.resetLogExcerpt ?? prev.resetLogExcerpt;
}
fs.writeFileSync(factsPath, JSON.stringify(facts, null, 2));
console.log(`\nCaptured ${shots.length} screenshots.`);
console.log(JSON.stringify(facts, null, 2));
