// Screenshots for the "Teen Admin Levels — Asaan Guide" (build-levels.mjs).
//
//   node capture-levels.mjs        (or: npm run levels:capture)
//
// READ-ONLY: it only signs in as demo users, opens pages, menus and drawers,
// and closes them again (Cancel / Escape). Nothing is submitted or saved.
// Development only — refuses to run against anything but localhost.

import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const OUT = path.join(HERE, 'screenshots-levels');
const BASE = process.env.ERP_URL ?? 'http://localhost:3000';
const PW = 'password';
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';

if (!/^https?:\/\/(localhost|127\.0\.0\.1)/.test(BASE)) throw new Error('capture-levels.mjs only runs against localhost.');
fs.mkdirSync(OUT, { recursive: true });

const facts = { issues: [], notes: [] };
const browser = await chromium.launch({ executablePath: EDGE });

const HIDE_DEV = () => {
  const add = () => { const s = document.createElement('style'); s.textContent = 'nextjs-portal{display:none!important}'; (document.head || document.documentElement).appendChild(s); };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add); else add();
};

async function settle(page, extra = 900) {
  await page.waitForLoadState('networkidle', { timeout: 30000 }).catch(() => {});
  for (let i = 0; i < 40; i++) {
    const n = await page.evaluate(() => [...document.querySelectorAll('[class*="skeleton" i]')].filter((e) => { const r = e.getBoundingClientRect(); return r.width && r.height; }).length).catch(() => 0);
    if (!n) break;
    await page.waitForTimeout(500);
  }
  await page.waitForTimeout(extra);
}

async function login(email) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, locale: 'en-US' });
  await context.addInitScript(HIDE_DEV);
  const page = await context.newPage();
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
  await page.fill('#email', email);
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  for (let i = 0; i < 90 && /\/login/.test(page.url()); i++) await page.waitForTimeout(500);
  await settle(page);
  return { context, page };
}

const go = async (page, url, extra) => { await page.goto(BASE + url, { waitUntil: 'domcontentloaded', timeout: 60000 }); await settle(page, extra); };

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

async function shot(page, name) {
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(OUT, `${name}.png`), type: 'png' });
  await clearMarks(page);
  console.log('  ✓', name);
}

async function step(title, fn) {
  console.log(title);
  try { await fn(); } catch (e) {
    const msg = String(e.message ?? e).split('\n')[0];
    console.log('  ✗', msg);
    facts.issues.push({ step: title, error: msg });
  }
}

const rowActions = async (page, text) => {
  const row = page.locator('tbody tr').filter({ hasText: text }).first();
  await row.waitFor({ timeout: 15000 });
  return row.getByRole('button', { name: 'Row actions' });
};

// ---------------------------------------------------------------- Level 1: platform
const plat = await login('platform@erp.test');
await step('1. Platform landing', async () => {
  facts.notes.push(`platform@erp.test landed on ${plat.page.url().replace(BASE, '')}`);
  await shot(plat.page, 'L01-platform-landing');
});
await step('2. Companies row menu', async () => {
  await go(plat.page, '/platform/companies');
  await (await rowActions(plat.page, 'Northwind')).click();
  const menu = plat.page.getByRole('menu').last();
  await menu.waitFor();
  await mark(plat.page, menu);
  await shot(plat.page, 'L02-companies-row-menu');
  await plat.page.keyboard.press('Escape');
});
await step('3. Platform users', async () => {
  await go(plat.page, '/platform/users');
  await shot(plat.page, 'L03-platform-users');
});
await step('4. Platform users row menu (owner view)', async () => {
  await (await rowActions(plat.page, 'admin@northwind.test')).click();
  const menu = plat.page.getByRole('menu').last();
  await menu.waitFor();
  await mark(plat.page, menu);
  await shot(plat.page, 'L04-platform-user-menu');
  await plat.page.keyboard.press('Escape');
});
await step('5. Own row has no actions', async () => {
  const row = plat.page.locator('tbody tr').filter({ hasText: 'platform@erp.test' }).first();
  await row.waitFor({ timeout: 15000 });
  const n = await row.getByRole('button', { name: 'Row actions' }).count();
  facts.notes.push(`Own row (platform@erp.test) row-actions buttons: ${n}`);
  await mark(plat.page, row);
  await shot(plat.page, 'L05-platform-users-self');
});
await step('6. System', async () => {
  await go(plat.page, '/platform/system', 1500);
  await shot(plat.page, 'L06-system');
});
await step('7. Platform dashboard (no company data)', async () => {
  await go(plat.page, '/dashboard');
  await shot(plat.page, 'L07-platform-dashboard');
});
await plat.context.close();

// ---------------------------------------------------------------- Level 2: owner
const owner = await login('owner@northwind.test');
await step('8. Owner opens /platform -> denied', async () => {
  await go(owner.page, '/platform/companies');
  await shot(owner.page, 'L08-owner-platform-denied');
});
await step('9. Owner: row menu for an Administrator', async () => {
  await go(owner.page, '/access/users');
  await (await rowActions(owner.page, 'admin@northwind.test')).click();
  const menu = owner.page.getByRole('menu').last();
  await menu.waitFor();
  await mark(owner.page, menu);
  await shot(owner.page, 'L09-owner-user-menu');
  await owner.page.keyboard.press('Escape');
});
await step('10. Owner: user menu (company switcher / account)', async () => {
  await go(owner.page, '/dashboard', 1500);
  await shot(owner.page, 'L10-owner-dashboard');
});
await owner.context.close();

// ---------------------------------------------------------------- Level 3: admin
const admin = await login('admin@northwind.test');
await step('11. Admin users page', async () => {
  await go(admin.page, '/access/users');
  const ownerRow = admin.page.locator('tbody tr').filter({ hasText: 'owner@northwind.test' }).first();
  await mark(admin.page, ownerRow);
  await shot(admin.page, 'L11-admin-users');
});
await step('12. Admin: Company Administrator role (own role)', async () => {
  await go(admin.page, '/access/roles');
  const card = admin.page.locator('article, [class*="card" i]').filter({ hasText: 'Company Administrator' }).filter({ has: admin.page.getByRole('button', { name: /^(Edit|View)$/ }) }).last();
  await card.getByRole('button', { name: /^(Edit|View)$/ }).first().click();
  const dlg = admin.page.getByRole('dialog').last();
  await dlg.waitFor({ timeout: 15000 });
  await settle(admin.page, 600);
  await shot(admin.page, 'L12-admin-own-role');
  await admin.page.keyboard.press('Escape');
});
await admin.context.close();

// ---------------------------------------------------------------- staff
const emp = await login('employee@northwind.test');
await step('13. Employee opens Users -> denied', async () => {
  await go(emp.page, '/access/users');
  await shot(emp.page, 'L13-employee-users-denied');
});
await emp.context.close();

await browser.close();
fs.writeFileSync(path.join(OUT, '_facts.json'), JSON.stringify(facts, null, 2));
console.log('\nissues:', facts.issues.length, facts.notes);
