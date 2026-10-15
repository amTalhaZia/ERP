// Builds docs/Enterprise-ERP-Admin-Access-Guide.pdf from ./guide-access/*.mjs
// and the screenshots captured by capture-access.mjs (./screenshots-access).
//
//   node build-access.mjs        (or: npm run access)

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { buildPdf } from './guide/builder.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SRC = path.join(HERE, 'guide-access');

const chapters = [];
for (const f of fs.readdirSync(SRC).filter((x) => /^\d+.*\.mjs$/.test(x)).sort()) {
  chapters.push((await import(pathToFileURL(path.join(SRC, f)).href)).default);
}

const cover = (date) => `<section class="cover">
  <div class="cover-band"></div>
  <div class="cover-logo"><svg viewBox="0 0 24 24" width="54" height="54" fill="none" stroke="#a5b4fc" stroke-width="1.6"><path d="M12 2 21 7v10l-9 5-9-5V7z"/></svg><span>Enterprise ERP</span></div>
  <div class="cover-main">
    <div class="cover-kicker">Administrator handbook · Version 1.0</div>
    <h1 class="cover-title">Admin hierarchy<br>&amp; access dena</h1>
    <p class="cover-sub">Sab se upar wala admin apne neeche admins kaise banata hai, aur har level apne neeche walon ko access kaise deta hai — har click ke screenshot ke sath.</p>
    <ul class="cover-points">
      <li>Platform Owner → Company + Company Owner</li>
      <li>Company Owner → Company Administrator ("chhota admin")</li>
      <li>Admin → branch-restricted HR, Accountant, Department Manager</li>
      <li>Custom roles, kis role ko kya dikhta hai, access wapas lena, audit log</li>
    </ul>
  </div>
  <div class="cover-foot">
    <div><b>Version</b> 1.0</div>
    <div><b>Date</b> ${date}</div>
    <div><b>Audience</b> Owners &amp; administrators</div>
  </div>
</section>`;

await buildPdf({
  shotsDir: path.join(HERE, 'screenshots-access'),
  cacheDir: path.join(HERE, '.cache', 'access'),
  chapters,
  outPdf: path.resolve(HERE, '..', 'Enterprise-ERP-Admin-Access-Guide.pdf'),
  docTitle: 'Enterprise ERP — Admin Hierarchy &amp; Access Guide',
  shortTitle: 'Admin Hierarchy &amp; Access · v1.0',
  cover,
  css: fs.readFileSync(path.join(HERE, 'guide', 'style.css'), 'utf8') + fs.readFileSync(path.join(SRC, 'extra.css'), 'utf8'),
});
