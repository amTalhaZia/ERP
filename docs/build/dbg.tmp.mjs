import { chromium } from 'playwright-core';
const S = 'C:/Users/Adnan/AppData/Local/Temp/claude/c--Users-Adnan-ERP/c6e635ea-3f80-4c28-9024-d0f98e016545/scratchpad/state-owner@northwind.test.json';
const browser = await chromium.launch({ executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe' });
const page = await (await browser.newContext({ viewport: { width: 1440, height: 900 }, storageState: S })).newPage();
page.on('console', m => { if (m.type()==='error') console.log('console:', m.text().slice(0,600)); });
page.on('pageerror', e => console.log('pageerror:', e.stack.slice(0,1500)));
page.on('response', r => { if (r.status() >= 400) console.log('HTTP', r.status(), r.url()); });
for (const u of ['/finance/reports']) {
  console.log('---', u);
  await page.goto('http://localhost:3000'+u, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1500);
  console.log((await page.innerText('main').catch(()=>page.innerText('body'))).slice(0,200));
}
await browser.close();
