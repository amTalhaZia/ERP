import { chromium } from 'playwright-core';
import fs from 'fs';
const [,, email, routesArg, out] = process.argv;
const routes = routesArg.split(',');
const S = 'C:/Users/Adnan/AppData/Local/Temp/claude/c--Users-Adnan-ERP/c6e635ea-3f80-4c28-9024-d0f98e016545/scratchpad/state-' + email + '.json';
const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
let ctx;
if (fs.existsSync(S)) ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, storageState: S });
else {
  ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const p = await ctx.newPage();
  await p.goto('http://localhost:3000/login', { waitUntil: 'networkidle' });
  await p.fill('#email', email); await p.fill('#password', 'password');
  await p.click('button[type=submit]');
  for (let i = 0; i < 60 && !/dashboard|platform/.test(p.url()); i++) await p.waitForTimeout(1000);
  console.log('after login', p.url(), (await p.innerText('body')).slice(0,200));
  await ctx.storageState({ path: S }); await p.close();
}
const page = await ctx.newPage();
const res = {};
for (const r of routes) {
  try {
    await page.goto('http://localhost:3000' + r, { waitUntil: 'networkidle', timeout: 60000 });
    await page.waitForTimeout(1200);
    res[r] = await page.evaluate(() => {
      const vis = el => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0; };
      const main = document.querySelector('main') || document.body;
      const t = s => [...main.querySelectorAll(s)].filter(vis).map(e => e.innerText.trim().replace(/\s+/g,' ')).filter(Boolean);
      const links = [...main.querySelectorAll('a[href]')].map(a => a.getAttribute('href')).filter(h => /\/\d+/.test(h)).slice(0, 5);
      return { url: location.pathname, h1: t('h1'), h2: t('h2,h3').slice(0,15), buttons: [...new Set(t('button'))].slice(0,40), tabs: t('[role=tab]'), links, alerts: t('[role=alert]'), text: main.innerText.slice(0, 700).replace(/\s+/g,' ') };
    });
  } catch (e) { res[r] = { error: String(e).slice(0, 200) }; }
}
fs.writeFileSync(out, JSON.stringify(res, null, 1));
await browser.close();
