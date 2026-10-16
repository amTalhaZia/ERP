// Builds docs/Teen-Admin-Levels-Guide.pdf ("Teen Admin Levels — Asaan Guide",
// Hinglish) from ./guide-levels/*.mjs.
//
//   node build-levels.mjs        (or: npm run levels)
//
// Screenshots: new ones from capture-levels.mjs (./screenshots-levels) plus a
// few reused from ./screenshots and ./screenshots-access. The ones actually
// referenced are staged into .cache/levels-shots so the shared builder only
// converts what this guide needs.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { buildPdf } from './guide/builder.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SRC = path.join(HERE, 'guide-levels');
const SHOT_DIRS = ['screenshots-levels', 'screenshots-access', 'screenshots'].map((d) => path.join(HERE, d));
const STAGE = path.join(HERE, '.cache', 'levels-shots');

const chapters = [];
for (const f of fs.readdirSync(SRC).filter((x) => /^\d+.*\.mjs$/.test(x)).sort()) {
  chapters.push((await import(pathToFileURL(path.join(SRC, f)).href)).default);
}

// Stage only the screenshots the chapters reference: fig('name', ...)
fs.rmSync(STAGE, { recursive: true, force: true });
fs.mkdirSync(STAGE, { recursive: true });
const wanted = new Set();
for (const f of fs.readdirSync(SRC).filter((x) => x.endsWith('.mjs'))) {
  for (const m of fs.readFileSync(path.join(SRC, f), 'utf8').matchAll(/fig\(\s*'([^']+)'/g)) wanted.add(m[1]);
}
for (const name of wanted) {
  const dir = SHOT_DIRS.find((d) => fs.existsSync(path.join(d, `${name}.png`)));
  if (!dir) { console.log('WARNING: screenshot not found:', name); continue; }
  const src = path.join(dir, `${name}.png`);
  const dst = path.join(STAGE, `${name}.png`);
  fs.copyFileSync(src, dst);
  const t = fs.statSync(src).mtime;
  fs.utimesSync(dst, t, t);
}

const cover = (date) => `<section class="cover">
  <div class="cover-band"></div>
  <div class="cover-logo"><svg viewBox="0 0 24 24" width="54" height="54" fill="none" stroke="#a5b4fc" stroke-width="1.6"><path d="M12 2 21 7v10l-9 5-9-5V7z"/></svg><span>Enterprise ERP</span></div>
  <div class="cover-main">
    <div class="cover-kicker">Business owner ke liye · Hinglish · Version 1.0</div>
    <h1 class="cover-title">Teen Admin Levels —<br>Asaan Guide</h1>
    <p class="cover-sub">3 tarah ke admin kyun hain, kaun kya karta hai, kaun kise banata hai, aur Platform ke andar 3 cheezein (Companies, Platform users, System) kya hain — screenshots aur sawal-jawab ke sath.</p>
    <ul class="cover-points">
      <li>Level 1 — Platform Owner / Platform Admin (mall ka maalik)</li>
      <li>Level 2 — Company Owner (dukaan ka maalik)</li>
      <li>Level 3 — Company Admin + staff roles (dukaan ka manager aur staff)</li>
      <li>"Kaun kise bana sakta hai" matrix, security rules, 30+ sawal-jawab</li>
    </ul>
  </div>
  <div class="cover-foot">
    <div><b>Version</b> 1.0</div>
    <div><b>Date</b> ${date}</div>
    <div><b>Audience</b> Business owner (non-technical)</div>
  </div>
</section>`;

await buildPdf({
  shotsDir: STAGE,
  cacheDir: path.join(HERE, '.cache', 'levels'),
  chapters,
  outPdf: path.resolve(HERE, '..', 'Teen-Admin-Levels-Guide.pdf'),
  docTitle: 'Teen Admin Levels — Asaan Guide',
  shortTitle: 'Teen Admin Levels · v1.0',
  cover,
  css: fs.readFileSync(path.join(HERE, 'guide', 'style.css'), 'utf8')
    + fs.readFileSync(path.join(HERE, 'guide-access', 'extra.css'), 'utf8')
    + fs.readFileSync(path.join(SRC, 'extra.css'), 'utf8'),
});
