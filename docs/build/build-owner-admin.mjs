// Builds docs/Owner-vs-Admin-Guide.pdf — Company Owner vs Company Admin (Hinglish).
//   node build-owner-admin.mjs
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const here = dirname(fileURLToPath(import.meta.url));
const OUT = resolve(here, '..', 'Owner-vs-Admin-Guide.pdf');
const EDGE = process.env.PDF_BROWSER ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const img = (name) => `data:image/png;base64,${readFileSync(resolve(here, 'screenshots-access', name)).toString('base64')}`;
const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });

const Y = '<span class="y">✓</span>';
const N = '<span class="n">✗</span>';

const rows = [
  ['HR, Accounts, Sales, Stock, Projects, Reports — sab modules', Y, Y],
  ['Company settings, branches, departments', Y, Y],
  ['Staff invite karna (HR, Accountant, Employee…)', Y, Y],
  ['Payroll setup dekhna aur badalna (salary structure, tax table)', Y, Y],
  ['<b>Payroll chalana</b> — calculate, approve, finalize, pay', Y, N],
  ['<b>Kisi ko Owner banana</b>', Y, N],
  ['<b>Owner ko deactivate karna ya company se hatana</b>', Y, N],
  ['<b>Apne hi role ki permissions badalna</b> (apni taqat barhana)', Y, N],
  ['<b>Woh permission kisi ko dena jo khud ke paas nahi</b>', Y, N],
  ['Owner ke role ko badalna ya delete karna', `${N} <small>(locked)</small>`, N],
];

const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Owner vs Admin</title>
<style>
  @page { size: A4; margin: 18mm 16mm 18mm 16mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Inter, Arial, sans-serif; color: #0f172a; font-size: 10.5pt; line-height: 1.55; margin: 0; }
  h1 { font-size: 26pt; margin: 0 0 6px; color: #fff; letter-spacing: -0.5px; }
  h2 { font-size: 15pt; color: #312e81; margin: 26px 0 10px; padding-bottom: 6px; border-bottom: 2px solid #e0e7ff; break-after: avoid; }
  h3 { font-size: 12pt; margin: 18px 0 6px; color: #1e293b; break-after: avoid; }
  p { margin: 0 0 9px; }
  .cover { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4f46e5 100%); color: #e0e7ff; border-radius: 14px; padding: 34px 32px 30px; margin-bottom: 22px; }
  .cover .tag { display: inline-block; background: rgba(255,255,255,.15); color: #fff; border-radius: 999px; padding: 3px 12px; font-size: 9pt; margin-bottom: 14px; }
  .cover p { color: #c7d2fe; font-size: 12pt; margin: 0; }
  .cover .meta { margin-top: 18px; font-size: 9pt; color: #a5b4fc; }
  .duo { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin: 14px 0 6px; }
  .card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; break-inside: avoid; }
  .card.owner { border-top: 4px solid #4f46e5; background: #f8f9ff; }
  .card.admin { border-top: 4px solid #0d9488; background: #f5fdfc; }
  .card .ic { font-size: 22pt; line-height: 1; }
  .card h3 { margin: 6px 0 4px; }
  .card .who { color: #475569; font-size: 9.5pt; margin-bottom: 8px; }
  .card ul { margin: 0; padding-left: 18px; }
  table { width: 100%; border-collapse: collapse; margin: 8px 0 14px; break-inside: avoid; font-size: 10pt; }
  th { background: #312e81; color: #fff; text-align: left; padding: 8px 10px; font-weight: 600; }
  th.c, td.c { text-align: center; width: 92px; }
  td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  tr:nth-child(even) td { background: #f8fafc; }
  tr.hl td { background: #fef9c3 !important; }
  .y { color: #059669; font-weight: 700; font-size: 13pt; }
  .n { color: #dc2626; font-weight: 700; font-size: 13pt; }
  small { color: #64748b; }
  .callout { border-radius: 10px; padding: 11px 14px; margin: 12px 0; break-inside: avoid; border-left: 4px solid; }
  .tip { background: #eef2ff; border-color: #4f46e5; }
  .warn { background: #fffbeb; border-color: #d97706; }
  .sec { background: #ecfdf5; border-color: #059669; }
  .callout b:first-child { display: block; margin-bottom: 3px; }
  figure { margin: 12px 0 16px; break-inside: avoid; }
  figure img { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; display: block; }
  figcaption { font-size: 9pt; color: #475569; margin-top: 5px; text-align: center; }
  .pb { break-before: page; }
  ol li, ul li { margin-bottom: 4px; }
  .flow { display: flex; align-items: stretch; gap: 8px; margin: 10px 0 14px; break-inside: avoid; }
  .flow div { flex: 1; border: 1px solid #c7d2fe; background: #f5f7ff; border-radius: 10px; padding: 10px; font-size: 9.5pt; text-align: center; }
  .flow .arrow { flex: 0 0 18px; border: 0; background: none; padding: 0; display: flex; align-items: center; justify-content: center; color: #4f46e5; font-size: 16pt; }
  code { font-family: Consolas, monospace; background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: 9.5pt; }
</style></head><body>

<div class="cover">
  <div class="tag">Enterprise ERP · Admin Guide</div>
  <h1>Company Owner vs Company Admin</h1>
  <p>Dono mein kya farq hai, kis ke paas kya taqat hai, aur kab kise kya role dena chahiye — asaan Hinglish mein.</p>
  <div class="meta">Version 1.0 · ${today}</div>
</div>

<h2>1. Ek nazar mein</h2>
<p>Company ke andar do sabse bari taqat wale roles hain: <b>Company Owner</b> aur <b>Company Admin</b>. Asaan alfaaz mein — <b>Owner maalik hai, Admin uska manager</b>. Admin rozana ka saara kaam sambhal leta hai, lekin aakhri faisle aur sabse sensitive kaam sirf Owner ke haath mein rehte hain.</p>

<div class="duo">
  <div class="card owner">
    <div class="ic">🏢</div>
    <h3>Company Owner</h3>
    <div class="who">Dukaan ka maalik · demo: <code>owner@northwind.test</code></div>
    <ul>
      <li>Company ke andar <b>100% access</b></li>
      <li>Uska role <b>locked</b> hai — koi uski taqat kam nahi kar sakta</li>
      <li>Payroll ka aakhri approval aur payment</li>
      <li>Sirf Owner hi kisi ko Owner bana ya hata sakta hai</li>
    </ul>
  </div>
  <div class="card admin">
    <div class="ic">🛡️</div>
    <h3>Company Admin</h3>
    <div class="who">Dukaan ka manager · demo: <code>admin@northwind.test</code></div>
    <ul>
      <li>Rozana ka sab kaam: HR, accounts, sales, stock, users</li>
      <li>Staff ko invite karna aur roles dena</li>
      <li><b>Payroll chala nahi sakta</b> (sirf setup)</li>
      <li>Owner ko chhed nahi sakta, apni taqat barha nahi sakta</li>
    </ul>
  </div>
</div>

<div class="callout tip"><b>💡 Yaad rakhne ka asool</b>Admin <b>sab kuch kar sakta hai</b> — siwaye do cheezon ke: (1) <b>paisa nikalne wala payroll ka aakhri qadam</b>, aur (2) <b>Owner ki kursi ko chhedna</b>.</div>

<h3>Login accounts (testing ke liye)</h3>
<table>
  <thead><tr><th style="width:34px">#</th><th>Role</th><th>Email</th><th>Access</th></tr></thead>
  <tbody>
    <tr><td>1</td><td>👑 <b>Platform Owner</b></td><td><code>platform@erp.test</code></td><td>Saari companies banana, band karna aur unke owner muqarrar karna <small>(company ka andar ka data nahi dekhta)</small></td></tr>
    <tr><td>2</td><td>🏢 <b>Company Owner</b></td><td><code>owner@northwind.test</code></td><td>Apni company mein <b>100% access</b>, sab kuch</td></tr>
    <tr><td>3</td><td>🛡️ <b>Company Admin</b></td><td><code>admin@northwind.test</code></td><td>Owner jaisa, magar payroll final nahi kar sakta aur kisi ko Owner nahi bana sakta</td></tr>
  </tbody>
</table>
<p><small>Teeno ka password: <code>password</code> · Login: <code>http://localhost:3000</code> · <b>Ye sirf demo/testing accounts hain</b> — asli istemal mein apne email aur strong password istemal karein.</small></p>

<h2>2. Poora farq — ek table mein</h2>
<table>
  <thead><tr><th>Kaam</th><th class="c">🏢 Owner</th><th class="c">🛡️ Admin</th></tr></thead>
  <tbody>
    ${rows.map(([k, o, a]) => `<tr${a === N && o === Y ? ' class="hl"' : ''}><td>${k}</td><td class="c">${o}</td><td class="c">${a}</td></tr>`).join('')}
  </tbody>
</table>
<p><small>Peeli lines woh kaam hain jo <b>sirf Owner</b> kar sakta hai.</small></p>

<h2 class="pb">3. Admin ko ye cheezein kyun nahi di gayin?</h2>

<h3>① Payroll chalana</h3>
<p>Salaries company ka sabse sensitive kaam hai — is mein <b>asal paisa bank se nikalta hai</b>. Is liye system mein payroll ke 5 qadam hain: <i>Draft → Calculated → Approved → Finalized → Paid</i>. Admin salary structure aur tax table tayyar kar sakta hai, lekin <b>calculate, approve, finalize aur pay</b> ki ijazat sirf Owner (aur jis ko Owner khud <code>payroll.process</code> permission de, jaise HR Manager) ke paas hai.</p>
<div class="callout sec"><b>🔒 Maker–checker</b>Jo shakhs payroll run <b>banata</b> hai, wo use khud <b>approve nahi</b> kar sakta — doosra banda approve karega. Is se ghalti ya dhoka pakda jata hai.</div>

<h3>② Owner banana ya hatana</h3>
<p>Agar Admin khud ko ya kisi aur ko Owner bana sakta, ya asli Owner ko company se nikal sakta — to wo <b>poori company par qabza</b> kar leta. Isliye ye kaam sirf Owner kar sakta hai. Agar Admin koshish kare to server <b>403 — access denied</b> ka jawab deta hai, chahe wo button chhupa ho ya koi seedha URL bhi bheje.</p>

<h3>③ Apni taqat khud barhana</h3>
<p>Admin <b>apne hi role</b> (Company Administrator) mein nayi permissions nahi daal sakta, aur kisi ko aisi permission nahi de sakta jo <b>uske apne paas na ho</b>. Asool simple hai: <b>"Jo tumhare paas nahi, wo tum kisi ko de bhi nahi sakte."</b> Sirf Owner is pabandi se azad hai.</p>

<figure>
  <img src="${img('a26-owner-role-locked.png')}" alt="Company Owner role locked">
  <figcaption>Figure 1 — Roles &amp; permissions page: <b>Company Owner</b> role par "Locked" likha hai. Is role ko koi edit ya delete nahi kar sakta.</figcaption>
</figure>

<h2 class="pb">4. Owner, Admin kaise banata hai</h2>
<div class="flow">
  <div><b>1.</b> Owner login karta hai</div><div class="arrow">→</div>
  <div><b>2.</b> Users &amp; roles → Users → <b>Invite user</b></div><div class="arrow">→</div>
  <div><b>3.</b> Naam, email, role: <b>Company Administrator</b></div><div class="arrow">→</div>
  <div><b>4.</b> Gmail par invitation → wo apna password banata hai</div>
</div>
<ol>
  <li>Owner ke account se login karein.</li>
  <li>Sidebar mein <b>Users &amp; roles → Users</b> par jayein aur upar right <b>Invite user</b> dabayein.</li>
  <li><b>Full name</b> aur <b>Work email</b> likhein.</li>
  <li><b>Roles</b> mein <b>Company Administrator</b> par tick lagayein.</li>
  <li>Sab branches ki access deni hai to <b>Access to all branches</b> on rehne dein; warna off karke sirf zaroori branch chunein.</li>
  <li><b>Send invitation</b> dabayein. Us shakhs ko Gmail par link jayega — wo <b>apna password khud</b> banayega. Owner ko uska password kabhi pata nahi chalta.</li>
</ol>
<figure>
  <img src="${img('a15-invite-sub-admin.png')}" alt="Invite a user as Company Administrator">
  <figcaption>Figure 2 — Invite user: (1) naam, (2) email, (3) <b>Company Administrator</b> role, (4) branches, (5) <b>Send invitation</b>.</figcaption>
</figure>

<h2 class="pb">5. Kab Admin rakhein, kab nahi?</h2>
<table>
  <thead><tr><th>Aapki situation</th><th>Mashwara</th></tr></thead>
  <tbody>
    <tr><td>Aap khud rozana sab kuch sambhalte hain</td><td>Admin ki zaroorat <b>nahi</b> — sirf Owner kaafi hai.</td></tr>
    <tr><td>Aap busy hain; koi bharose ka manager rozana kaam, users aur settings sambhale</td><td>Usay <b>Company Admin</b> bana dein. Payroll aur Owner wali taqat aapke paas rahegi.</td></tr>
    <tr><td>Do ya zyada barabar ke maalik (partners)</td><td>Dono ko <b>Company Owner</b> bana dein. (Ek se zyada Owner ho sakte hain.)</td></tr>
    <tr><td>Kisi ko sirf ek kaam dena hai (sirf HR ya sirf accounts)</td><td>Admin <b>mat</b> banayein — <b>HR Manager</b> ya <b>Accountant</b> jaisa chhota role dein.</td></tr>
  </tbody>
</table>

<div class="callout warn"><b>⚠️ Dhyan dein</b>Admin ke paas bahut taqat hai — users bana sakta hai, settings badal sakta hai, sab data dekh sakta hai. Ye role sirf <b>bharose ke shakhs</b> ko dein, aur Owner aur Admin dono <b>Settings → Security</b> mein <b>2FA on</b> zaroor karein.</div>

<h2>6. Aksar puche jane wale sawal</h2>
<h3>Kya ek company mein do Owner ho sakte hain?</h3>
<p>Haan. Owner kisi doosre user ko bhi Company Owner role de sakta hai — partners ke liye ye theek hai.</p>
<h3>Admin ne kisi ko ghalat role de diya to?</h3>
<p>Owner (ya Admin) <b>Users</b> page par us user ke saath <b>Edit access</b> se role badal sakta hai. Har tabdeeli <b>Audit log</b> mein likhi jaati hai — kis ne, kab, kya badla.</p>
<h3>Admin company chhod de to?</h3>
<p>Owner <b>Users</b> page se uski membership <b>deactivate</b> ya <b>Remove from company</b> kar de. Foran uska access khatam ho jata hai.</p>
<h3>Owner password bhool jaye to?</h3>
<p>Login page par <b>Forgot password?</b> — Gmail par reset link aayega. Kisi admin ko password batane ki zaroorat nahi.</p>
<h3>Kya Admin naya Admin bana sakta hai?</h3>
<p>Haan, kyunke Admin ki saari permissions uske apne paas hain. Lekin wo kisi ko <b>Owner</b> nahi bana sakta, aur na <b>payroll chalane</b> wali permission de sakta hai.</p>
<h3>Admin aur Platform Owner mein kya farq hai?</h3>
<p>Platform Owner <b>poore system</b> ka maalik hai (companies banana/band karna) lekin kisi company ka andar ka data nahi dekhta. Company Owner aur Admin <b>sirf apni company ke andar</b> kaam karte hain.</p>

<div class="callout tip"><b>🧪 Khud try karein</b>http://localhost:3000 par <code>owner@northwind.test</code> aur <code>admin@northwind.test</code> (password: <code>password</code>) se baari baari login karein. Admin se <b>Payroll → Payroll runs</b> mein koi run approve karne ki koshish karein — ijazat nahi milegi. <small>(Demo accounts sirf testing ke liye.)</small></div>

</body></html>`;

const browser = await chromium.launch({ executablePath: EDGE });
const page = await browser.newPage();
await page.setContent(html, { waitUntil: 'load' });
await page.pdf({
  path: OUT,
  format: 'A4',
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: '<div style="font-size:8px;width:100%;padding:0 16mm;color:#94a3b8;font-family:Segoe UI,sans-serif;">Enterprise ERP — Company Owner vs Company Admin</div>',
  footerTemplate: '<div style="font-size:8px;width:100%;padding:0 16mm;color:#94a3b8;font-family:Segoe UI,sans-serif;text-align:right;">Page <span class="pageNumber"></span> / <span class="totalPages"></span></div>',
  margin: { top: '18mm', bottom: '16mm', left: '0', right: '0' },
});
await browser.close();
console.log('PDF:', OUT);
