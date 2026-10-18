// Builds docs/ERP-CRUDs-aur-ENV-Guide.pdf — every module's CRUD screens and all
// environment settings (what they mean, which folder/file they go in). Hinglish.
//   node build-cruds-env.mjs
// No real secrets are written into the PDF (passwords/keys appear as placeholders).
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const here = dirname(fileURLToPath(import.meta.url));
const OUT = resolve(here, '..', 'ERP-CRUDs-aur-ENV-Guide.pdf');
const BROWSER = process.env.PDF_BROWSER ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const today = new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'long', year: 'numeric' });

const Y = '<span class="y">✓</span>';
const N = '<span class="n">—</span>';
const crud = (s) => [...s].map((c) => (c === '1' ? Y : N)).join('</td><td class="c">');

// [cheez, menu, CRUD flags "CRUD" as 1/0, khaas actions, kaun]
const MODULES = [
  ['🏢 Company & Organisation', [
    ['Company profile (naam, logo, address)', 'Company → Profile & settings', '0110', 'Logo upload / remove', 'Owner, Admin'],
    ['Branches', 'Company → Branches', '1111', 'Map pe location', 'Owner, Admin'],
    ['Departments', 'Company → Departments', '1111', 'Tree (parent / sub-department)', 'Owner, Admin'],
    ['Locations map', 'Company → Locations map', '0100', 'Branches, customers, suppliers map pe', 'Jinke paas branches dekhne ki ijazat'],
  ]],
  ['🔐 Users & Roles', [
    ['Users (login accounts)', 'Users & roles → Users', '1111', 'Invite (email jaati hai), invite dobara bhejna, deactivate, branch limit', 'Owner, Admin'],
    ['Roles & permissions', 'Users & roles → Roles & permissions', '1111', 'Existing role se copy; Company Owner role locked', 'Owner, Admin'],
  ]],
  ['👥 HR', [
    ['Employees', 'HR → Employees', '1111', 'Terminate / reactivate, photo, "Reports to" badalna, org chart', 'HR Manager, Owner, Admin'],
    ['Employee documents', 'Employee profile → Documents', '1101', 'Upload, download', 'HR'],
    ['Employment contracts', 'Employee profile → Contracts', '1111', 'Contract file', 'HR'],
    ['Designations (job titles)', 'HR → Employees → "Manage designations"', '1111', '—', 'HR'],
    ['Attendance', 'HR → Attendance', '1111', 'Bulk entry, check-in / check-out, summary', 'HR (record), sab (apni)'],
    ['Leave requests', 'HR → Leave', '1100', 'Approve, reject, cancel, attachment', 'Employee (maangna), HR / manager (approve)'],
    ['Leave types & balances', 'HR → Leave', '1111', 'Saal ke balances banana, adjust', 'HR'],
    ['Shifts', 'HR → Shifts & holidays', '1111', 'Employee ko shift dena', 'HR'],
    ['Public holidays', 'HR → Shifts & holidays', '1111', 'Branch-wise; employees ko notification', 'HR (banana), sab (dekhna)'],
    ['Performance reviews', 'HR → Performance', '1111', 'Submit, acknowledge', 'HR, manager, employee (apna)'],
  ]],
  ['💰 Payroll', [
    ['Pay components (allowance / deduction)', 'Payroll → Salary structures', '1111', '—', 'HR, Owner'],
    ['Salary structures', 'Payroll → Salary structures', '1111', '—', 'HR, Owner'],
    ['Tax tables', 'Payroll → Salary structures', '1111', 'Brackets', 'HR, Owner'],
    ['Employee salary', 'Employee profile', '1100', 'Nayi salary (purani history rehti hai)', 'HR'],
    ['Payroll runs', 'Payroll → Payroll runs', '1101', 'Calculate → Approve → Finalize → Pay; cancel, export', 'HR (chalana), Owner (approve)'],
    ['Payslips', 'Payroll run → Payslips / My payslips', '0100', 'PDF download', 'HR; employee sirf apni'],
  ]],
  ['📒 Accounting', [
    ['Chart of accounts', 'Accounting → Chart of accounts', '1111', 'Account ledger, default accounts', 'Accountant, Owner, Admin'],
    ['Journal entries', 'Accounting → Journal entries', '1111', 'Post, reverse (draft hi edit/delete hoti hai)', 'Accountant'],
    ['Bank & cash accounts', 'Accounting → Bank & cash', '1111', '—', 'Accountant'],
    ['Tax rates', 'Accounting → Tax rates', '1111', '—', 'Accountant'],
    ['Financial years & periods', 'Accounting → Financial periods', '1100', 'Period close / reopen', 'Accountant, Owner'],
    ['Financial reports', 'Accounting → Financial reports', '0100', 'P&L, Balance sheet, Trial balance, Cash flow, General ledger, Aging', 'Accountant, Owner, Auditor'],
  ]],
  ['🧾 Receivables & Payables', [
    ['Customer invoices', 'Receivables → Customer invoices', '1111', 'Submit → Approve (post) → Void; PDF; aging', 'Banana: Admin/Sales; approve: Accountant'],
    ['Receipts (customer se paisa)', 'Receivables → Receipts', '1100', 'Invoices pe allocate, void', 'Accountant'],
    ['Supplier bills', 'Payables → Supplier bills', '1111', 'Submit → Approve → Void', 'Banana: Admin/Procurement; approve: Accountant'],
    ['Payments (supplier ko paisa)', 'Payables → Payments', '1100', 'Bills pe allocate, void', 'Accountant'],
    ['Expense claims', 'Payables → Expense claims', '1111', 'Receipt upload, submit, approve, reject', 'Employee (claim), manager / accountant'],
  ]],
  ['📈 Sales & CRM', [
    ['Customers', 'Sales & CRM → Customers', '1111', 'Contacts, notes (communications), map, statement', 'Sales Manager, Admin'],
    ['Leads', 'Sales & CRM → Leads', '1111', 'Assign, convert (customer banana)', 'Sales'],
    ['Opportunities / Pipeline', 'Sales & CRM → Pipeline', '1111', 'Stage badalna (board), stages ka order', 'Sales'],
    ['Quotations', 'Sales & CRM → Quotations', '1111', 'Send, accept, reject, sales order mein convert', 'Sales'],
    ['Sales orders', 'Sales & CRM → Sales orders', '1111', 'Confirm, approve, cancel', 'Sales'],
    ['Deliveries', 'Sales & CRM → Deliveries', '1100', 'Order se banti hai; ship, deliver, cancel', 'Inventory / Sales'],
  ]],
  ['🛒 Procurement (kharidari)', [
    ['Suppliers', 'Procurement → Suppliers', '1111', 'Approve, contacts, map', 'Procurement, Admin'],
    ['Purchase requisitions', 'Procurement → Requisitions', '1111', 'Submit, approve, reject, cancel, PO banana', 'Koi bhi staff (maangna), manager (approve)'],
    ['Purchase orders', 'Procurement → Purchase orders', '1111', 'Submit, approve, send, cancel, close', 'Procurement'],
    ['Goods receipts', 'Procurement → Goods receipts', '1111', 'PO se banti hai; confirm (stock barhta hai), cancel', 'Procurement / Inventory'],
    ['Purchase returns', 'Procurement → Goods receipts', '1111', 'Confirm, cancel', 'Procurement'],
  ]],
  ['📦 Inventory', [
    ['Products', 'Inventory → Products', '1111', 'Image, stock, batches, movements, low-stock', 'Inventory Manager'],
    ['Product categories', 'Inventory → Products', '1111', 'Tree', 'Inventory'],
    ['Units of measure', 'Inventory → Products', '1111', '—', 'Inventory'],
    ['Warehouses', 'Inventory → Warehouses', '1111', 'Andar ki locations', 'Inventory'],
    ['Stock movements', 'Inventory → Movements / Stock levels', '1100', 'Receive, issue (in/out)', 'Inventory'],
    ['Stock transfers', 'Inventory → Transfers', '1111', 'Ship, receive, cancel', 'Inventory'],
    ['Stock adjustments', 'Inventory → Adjustments', '1111', 'Post (ginti theek karna)', 'Inventory'],
  ]],
  ['📁 Projects & Documents', [
    ['Projects', 'Projects', '1111', 'Board, summary, time summary', 'Manager, members'],
    ['Tasks', 'Project → Tasks', '1111', 'Subtasks, comments, board pe move, "My tasks"', 'Project members'],
    ['Milestones / Members / Time entries / Project expenses', 'Project ke andar', '1111', '—', 'Project members'],
    ['Document folders', 'Documents', '1111', '—', 'Jinke paas documents ki ijazat'],
    ['Documents', 'Documents', '1111', 'Naya version, download, share link', 'Jinke paas documents ki ijazat'],
  ]],
  ['🔔 Approvals, Reports, Audit, Settings', [
    ['Approvals inbox', 'Approvals', '0100', 'Har pending cheez ek jagah (leave, invoice, bill, PO…)', 'Approve karne wale'],
    ['Reports & exports', 'Reports & exports', '0100', 'Excel/CSV export, download', 'Report ki ijazat wale'],
    ['Audit log', 'Audit log', '0100', 'Sirf dekhna — koi edit/delete nahi kar sakta', 'Owner, Admin, Auditor'],
    ['Notifications', 'Upar bell 🔔', '0101', 'Read, sab read; email / in-app on-off', 'Har user (apni)'],
    ['My profile / Security', 'Avatar → My profile / Security', '0110', 'Password badalna, 2-step sign-in on/off', 'Har user (apna)'],
  ]],
  ['🛡️ Platform (sab se upar)', [
    ['Companies', 'Platform → Companies', '1111', 'Activate / deactivate, users & roles dekhna', 'Platform Owner / Admin'],
    ['Platform users', 'Platform → Platform users', '0110', 'Deactivate, platform role dena', 'Platform Owner'],
    ['System', 'Platform → System', '0100', 'Health, database size, har company ki storage', 'Platform staff'],
  ]],
];

const SECRET = '<i>•••• (secret)</i>';
// [setting, kya hai, local example, live example]
const BACKEND_ENV = [
  ['App', [
    ['APP_NAME', 'App ka naam (emails mein bhi)', 'Enterprise ERP', 'Enterprise ERP'],
    ['APP_ENV', 'Kahan chal raha hai: local / demo / production', 'local', 'demo (asli business: production)'],
    ['APP_KEY', 'Encryption ki chaabi — har installation ki apni', '<code>php artisan key:generate</code>', SECRET],
    ['APP_DEBUG', 'Error ki tafseel dikhaye? Live pe hamesha false', 'true', 'false'],
    ['APP_URL', 'Backend ka address', 'http://localhost:8000', 'https://erp-talha-six.vercel.app'],
  ]],
  ['Database', [
    ['DB_CONNECTION', 'Database ki qism', 'mysql', 'mysql'],
    ['DB_HOST', 'Database server ka address', '127.0.0.1', 'erp-mysql-…aivencloud.com'],
    ['DB_PORT', 'Port', '3306', '13388'],
    ['DB_DATABASE', 'Database ka naam', 'erp', 'defaultdb'],
    ['DB_USERNAME', 'Database user', 'erp_app', 'avnadmin'],
    ['DB_PASSWORD', 'Database password', SECRET, SECRET],
    ['DB_SSL_CA', 'SSL certificate file ka raasta (hosted DB)', '(khaali)', '(khud banta hai)'],
    ['DB_SSL_CA_PEM', 'Certificate ka text — sirf Vercel pe', '—', 'Aiven ka CA certificate'],
    ['DB_SSL_VERIFY', 'Certificate check karna', 'true', 'true'],
  ]],
  ['Login / Session', [
    ['FRONTEND_URL', 'Frontend ka address (email links isi pe jaate hain)', 'http://localhost:3000', 'https://erp-talha-six.vercel.app'],
    ['SANCTUM_STATEFUL_DOMAINS', 'Kis website se login cookie chalegi (bina https)', 'localhost:3000,127.0.0.1:3000', 'erp-talha-six.vercel.app'],
    ['CORS_ALLOWED_ORIGINS', 'Kaunsi website API ko call kar sakti hai', 'http://localhost:3000', '(proxy ki wajah se zaroori nahi)'],
    ['SESSION_DRIVER', 'Login session kahan save ho', 'database', 'database'],
    ['SESSION_LIFETIME', 'Kitne minute baad logout', '120', '120'],
    ['SESSION_DOMAIN', 'Cookie ka domain', 'localhost', '(khaali)'],
    ['SESSION_SECURE_COOKIE', 'Cookie sirf https pe', 'false', 'true'],
    ['SESSION_SAME_SITE', 'Cookie policy', 'lax', 'lax'],
    ['AUTH_PASSWORD_TIMEOUT', 'Sensitive kaam ke liye password dobara kitne second baad maange', '900', '900'],
    ['TRUSTED_PROXIES', 'Proxy (Vercel) ke peeche asli IP pehchanna', '(khaali)', '*'],
  ]],
  ['Queue / Cache / Logs / Files', [
    ['QUEUE_CONNECTION', 'Background kaam (emails) kaise chalein', 'database', 'sync (foran)'],
    ['CACHE_STORE', 'Cache kahan', 'database', 'database'],
    ['LOG_CHANNEL', 'Logs kahan likhein', 'stack', 'stderr (Vercel logs)'],
    ['LOG_LEVEL', 'Kitni tafseel', 'debug', 'error'],
    ['FILESYSTEM_DISK', 'Uploads kahan save hon', 'local', 'local (temporary)'],
  ]],
  ['Email (Gmail SMTP)', [
    ['MAIL_MAILER', 'Email bhejne ka tareeqa', 'smtp (ya log = sirf file mein)', 'smtp'],
    ['MAIL_HOST / MAIL_PORT', 'Mail server', 'smtp.gmail.com / 587', 'smtp.gmail.com / 587'],
    ['MAIL_USERNAME', 'Gmail address', 'aap ka Gmail', 'aap ka Gmail'],
    ['MAIL_PASSWORD', 'Gmail <b>App password</b> (asli Gmail password nahi)', SECRET, SECRET],
    ['MAIL_FROM_ADDRESS / MAIL_FROM_NAME', 'Email kis naam / address se jaye', 'aap ka Gmail / Enterprise ERP', 'same'],
  ]],
  ['ERP ki apni settings', [
    ['ERP_ALLOW_ADMIN_BOOTSTRAP', 'Production mein <code>erp:create-admin</code> chalne de', 'false', 'false (sirf pehle admin ke waqt true)'],
    ['ERP_RETAIN_AUDIT_DAYS', 'Audit log kitne din rakhein', '730', '730'],
    ['ERP_RETAIN_NOTIFICATIONS_DAYS', 'Purani notifications kitne din', '90', '90'],
    ['ERP_UPLOAD_MAX_KB', 'Upload ki max size', '(default)', '(default)'],
    ['DEMO_PASSWORD', 'Demo seeder ke accounts ka password', 'password', '(seeder chalate waqt)'],
    ['DEMO_COMPANIES', 'Demo seeder kitni companies banaye', '(sab)', '1'],
  ]],
];

const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>ERP CRUDs aur ENV</title>
<style>
  @page { size: A4; margin: 16mm 14mm; }
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Inter, Arial, sans-serif; color: #0f172a; font-size: 9.6pt; line-height: 1.5; margin: 0; }
  h1 { font-size: 25pt; margin: 0 0 6px; color: #fff; letter-spacing: -0.5px; }
  h2 { font-size: 15pt; color: #312e81; margin: 22px 0 8px; padding-bottom: 6px; border-bottom: 2px solid #e0e7ff; break-after: avoid; }
  h3 { font-size: 11.5pt; margin: 14px 0 6px; color: #1e293b; break-after: avoid; }
  p { margin: 0 0 8px; }
  .cover { background: linear-gradient(135deg, #1e1b4b 0%, #312e81 60%, #4f46e5 100%); color: #e0e7ff; border-radius: 14px; padding: 30px 30px 26px; margin-bottom: 18px; }
  .cover .tag { display: inline-block; background: rgba(255,255,255,.15); color: #fff; border-radius: 999px; padding: 3px 12px; font-size: 8.5pt; margin-bottom: 12px; }
  .cover p { color: #c7d2fe; font-size: 11pt; margin: 0; }
  .cover .meta { margin-top: 14px; font-size: 8.5pt; color: #a5b4fc; }
  .toc { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; font-size: 9.5pt; margin: 6px 0 4px; }
  table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; font-size: 8.8pt; }
  thead { display: table-header-group; }
  tr { break-inside: avoid; }
  th { background: #312e81; color: #fff; text-align: left; padding: 6px 8px; font-weight: 600; }
  td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  tr:nth-child(even) td { background: #f8fafc; }
  td.c, th.c { text-align: center; width: 26px; padding-left: 2px; padding-right: 2px; }
  .y { color: #059669; font-weight: 700; font-size: 11pt; }
  .n { color: #cbd5e1; }
  code { font-family: Consolas, 'Cascadia Mono', monospace; font-size: 8.6pt; background: #eef2ff; color: #3730a3; padding: 1px 5px; border-radius: 4px; }
  .callout { border-radius: 10px; padding: 10px 14px; margin: 10px 0; break-inside: avoid; }
  .callout b:first-child { display: block; margin-bottom: 3px; }
  .tip { background: #ecfdf5; border-left: 4px solid #10b981; }
  .warn { background: #fff7ed; border-left: 4px solid #f97316; }
  .info { background: #eff6ff; border-left: 4px solid #3b82f6; }
  .legend { display: flex; gap: 14px; flex-wrap: wrap; margin: 6px 0 10px; font-size: 9pt; }
  .legend span { background: #f1f5f9; border-radius: 999px; padding: 3px 10px; }
  pre.tree { background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 12px 16px; font-family: Consolas, monospace; font-size: 8.8pt; line-height: 1.55; break-inside: avoid; margin: 8px 0 12px; }
  pre.tree .hl { color: #fde047; font-weight: 700; }
  pre.tree .cm { color: #94a3b8; }
  .page-break { break-before: page; }
  .muted { color: #64748b; font-size: 8.5pt; }
</style></head><body>

<div class="cover">
  <span class="tag">Hinglish guide</span>
  <h1>ERP — CRUDs aur ENV</h1>
  <p>Har module mein kya bana / dekh / badal / mita sakte hain, aur saari settings (.env) kahan aur kaise daalni hain.</p>
  <div class="meta">Enterprise ERP · ${today} · 89 resources · 483 API routes</div>
</div>

<div class="toc">
  <div><b>Hissa 1 — CRUDs</b><br>1. CRUD kya hota hai<br>2. Har module ki CRUD list<br>3. Khaas buttons (Approve, Post, Pay…)</div>
  <div><b>Hissa 2 — ENV</b><br>4. ENV kya hai<br>5. Kis folder / file mein<br>6. Backend settings ki list<br>7. Frontend settings<br>8. Badalne ke baad kya karein</div>
</div>

<h2>1. CRUD kya hota hai?</h2>
<p>Har screen par chaar buniyadi kaam hote hain:</p>
<table>
  <thead><tr><th style="width:70px">Harf</th><th style="width:110px">Matlab</th><th>ERP mein kaise</th></tr></thead>
  <tbody>
    <tr><td><b>C</b></td><td>Create — banana</td><td>"New employee", "New invoice" jaisa button</td></tr>
    <tr><td><b>R</b></td><td>Read — dekhna</td><td>List page + kisi row pe click karke detail</td></tr>
    <tr><td><b>U</b></td><td>Update — badalna</td><td>"Edit" button ya row ke ⋯ menu se</td></tr>
    <tr><td><b>D</b></td><td>Delete — mitana</td><td>⋯ menu → Delete (aksar confirm maangta hai)</td></tr>
  </tbody>
</table>
<div class="callout info"><b>Dhyan rahe</b>Paison wali cheezein (approved invoice, posted journal, paid payroll) <b>delete nahi hoti</b> — unhein <b>Void</b> ya <b>Reverse</b> kiya jata hai taake hisaab saaf rahe. Sirf <b>Draft</b> wali cheez edit/delete hoti hai. Har kaam <b>Audit log</b> mein likha jata hai.</div>
<div class="legend"><span>${Y} = ho sakta hai</span><span>${N} = yahan nahi (kisi aur tareeqe se, ya mumkin nahi)</span><span>"Kaun" = default roles; Owner har cheez kar sakta hai</span></div>

<h2>2. Har module ki CRUD list</h2>
${MODULES.map(([title, rows]) => `
<h3>${title}</h3>
<table>
  <thead><tr><th style="width:24%">Cheez</th><th style="width:22%">Menu mein kahan</th><th class="c">C</th><th class="c">R</th><th class="c">U</th><th class="c">D</th><th>Khaas buttons</th><th style="width:17%">Kaun</th></tr></thead>
  <tbody>${rows.map(([thing, menu, flags, extra, who]) => `<tr><td><b>${thing}</b></td><td>${menu}</td><td class="c">${crud(flags)}</td><td>${extra}</td><td>${who}</td></tr>`).join('')}</tbody>
</table>`).join('')}

<h2>3. Khaas buttons — ek cheez ka safar</h2>
<table>
  <thead><tr><th style="width:22%">Cheez</th><th>Qadam (left se right)</th></tr></thead>
  <tbody>
    <tr><td><b>Customer invoice</b></td><td>Draft → <b>Submit</b> → Accountant <b>Approve</b> (accounts mein post) → Receipt aaye to <b>Paid</b> · galti ho to <b>Void</b></td></tr>
    <tr><td><b>Supplier bill</b></td><td>Draft → <b>Submit</b> → <b>Approve</b> → Payment → <b>Paid</b> · galti ho to <b>Void</b></td></tr>
    <tr><td><b>Payroll run</b></td><td>Create → <b>Calculate</b> → Owner <b>Approve</b> → <b>Finalize</b> (payslips jaari) → <b>Pay</b> (bank se)</td></tr>
    <tr><td><b>Leave request</b></td><td>Employee maangta hai → HR / manager <b>Approve</b> ya <b>Reject</b> · employee <b>Cancel</b> bhi kar sakta hai</td></tr>
    <tr><td><b>Purchase (kharidari)</b></td><td>Requisition → Approve → <b>Purchase order</b> → Send → <b>Goods receipt</b> confirm (stock barhta) → Bill → Payment</td></tr>
    <tr><td><b>Sale (bechna)</b></td><td>Lead → Opportunity → <b>Quotation</b> → Accept → <b>Sales order</b> → <b>Delivery</b> (stock ghatta) → Invoice → Receipt</td></tr>
    <tr><td><b>Journal entry</b></td><td>Draft → <b>Post</b> · galti ho to <b>Reverse</b> (ulti entry), delete nahi</td></tr>
  </tbody>
</table>

<h2 class="page-break">4. ENV kya hai?</h2>
<p><b>ENV (environment) settings</b> woh cheezein hain jo har jagah alag hoti hain — database ka address, password, email ka account, website ka link. Inhein code mein nahi likhte; ek alag file (<code>.env</code>) ya hosting ki settings mein rakhte hain. Code wahi rehta hai, sirf settings badalti hain.</p>
<div class="callout warn"><b>Sab se zaroori rule</b><code>.env</code> file mein asli passwords hote hain — <b>isay kabhi GitHub pe commit na karein</b> (project mein yeh pehle se <code>.gitignore</code> mein hai). GitHub pe sirf <code>.env.example</code> jata hai, jis mein <b>koi asli password nahi</b> hota.</div>

<h2>5. ENV kis folder / file mein daalni hai</h2>
<pre class="tree">ERP/
├── backend/
│   ├── <span class="hl">.env</span>                  <span class="cm">← LOCAL backend settings (aap banate hain, GitHub pe nahi jaati)</span>
│   └── .env.example          <span class="cm">← namoona (GitHub pe hai, bina password)</span>
├── frontend/
│   ├── <span class="hl">.env.local</span>            <span class="cm">← LOCAL frontend settings (aap banate hain)</span>
│   └── .env.example          <span class="cm">← namoona</span>
└── deployment/
    └── backend.env.production.example   <span class="cm">← apne server (VPS) ke liye namoona</span></pre>

<table>
  <thead><tr><th style="width:22%">Kahan chal raha hai</th><th style="width:30%">Settings kahan daalni hain</th><th>Kaise banayein</th></tr></thead>
  <tbody>
    <tr><td><b>Apna PC — backend</b></td><td><code>backend/.env</code></td><td><code>backend/.env.example</code> ki copy banayein, naam <code>.env</code> rakhein, values bharein, phir <code>php artisan key:generate</code></td></tr>
    <tr><td><b>Apna PC — frontend</b></td><td><code>frontend/.env.local</code></td><td><code>frontend/.env.example</code> ki copy, naam <code>.env.local</code></td></tr>
    <tr><td><b>Live — backend</b></td><td>vercel.com → <b>erp-talha-api</b> → Settings → <b>Environment Variables</b></td><td>Koi file nahi — har setting yahan ek line; password wali "Encrypted"</td></tr>
    <tr><td><b>Live — frontend</b></td><td>vercel.com → <b>erp-talha</b> → Settings → Environment Variables</td><td>Sirf <code>BACKEND_URL</code></td></tr>
    <tr><td><b>Live — database viewer</b></td><td>vercel.com → <b>erp-talha-db</b> → Environment Variables</td><td><code>DBVIEW_USER</code>, <code>DBVIEW_PASSWORD</code>, <code>DBVIEW_SERVER</code></td></tr>
    <tr><td><b>Apna server (VPS)</b></td><td>server pe <code>backend/.env</code></td><td><code>deployment/backend.env.production.example</code> se copy — <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code></td></tr>
  </tbody>
</table>

<h3>Windows pe copy kaise karein (PowerShell)</h3>
<pre class="tree">cd C:\\Users\\Adnan\\ERP
copy backend\\.env.example backend\\.env
copy frontend\\.env.example frontend\\.env.local
cd backend
php artisan key:generate          <span class="cm"># APP_KEY khud bhar deta hai</span></pre>

<h2>6. Backend settings (<code>backend/.env</code> / Vercel erp-talha-api)</h2>
<p class="muted">Asli passwords is PDF mein nahi likhe — un ki jagah "•••• (secret)". Asli values aap ki local <code>.env</code> aur Vercel ki settings mein hain.</p>
${BACKEND_ENV.map(([group, rows]) => `
<h3>${group}</h3>
<table>
  <thead><tr><th style="width:24%">Setting</th><th style="width:34%">Kya hai</th><th style="width:21%">Local (PC)</th><th>Live (Vercel)</th></tr></thead>
  <tbody>${rows.map(([k, what, local, live]) => `<tr><td><code>${k}</code></td><td>${what}</td><td>${local}</td><td>${live}</td></tr>`).join('')}</tbody>
</table>`).join('')}

<h2>7. Frontend settings (<code>frontend/.env.local</code> / Vercel erp-talha)</h2>
<table>
  <thead><tr><th style="width:24%">Setting</th><th style="width:40%">Kya hai</th><th style="width:18%">Local</th><th>Live</th></tr></thead>
  <tbody>
    <tr><td><code>NEXT_PUBLIC_API_URL</code></td><td>Browser backend ko kis address pe call kare</td><td>http://localhost:8000</td><td>(khaali — khud set hota hai)</td></tr>
    <tr><td><code>BACKEND_URL</code></td><td>Live pe frontend backend ko apne andar se aage bhejta hai (proxy) — login cookie isi se chalti hai</td><td>(nahi chahiye)</td><td>https://erp-talha-api.vercel.app</td></tr>
  </tbody>
</table>
<div class="callout info"><b>NEXT_PUBLIC_ kya hai?</b>Jis setting ke naam ke shuru mein <code>NEXT_PUBLIC_</code> ho woh <b>browser tak pahunchti hai</b> — is liye usmein kabhi password na rakhein. Frontend mein koi password nahi hota; saare secrets backend ke paas hain.</div>

<h2>8. Setting badalne ke baad kya karein</h2>
<table>
  <thead><tr><th style="width:26%">Kahan badli</th><th>Asar lane ke liye</th></tr></thead>
  <tbody>
    <tr><td><code>backend/.env</code> (PC)</td><td><code>php artisan config:clear</code>, phir backend band karke dobara chalayein (<code>start-dev.ps1</code>)</td></tr>
    <tr><td><code>frontend/.env.local</code> (PC)</td><td>Frontend (npm run dev) band karke dobara chalayein</td></tr>
    <tr><td>Vercel settings (live)</td><td>Project → <b>Deployments</b> → sab se upar wali → ⋯ → <b>Redeploy</b> (sirf save karne se asar nahi hota)</td></tr>
  </tbody>
</table>

<div class="callout tip"><b>Yaad rakhne ki 4 baatein</b>
1) <code>.env</code> kabhi commit nahi · 2) Live pe <code>APP_DEBUG=false</code> · 3) Gmail ke liye <b>App password</b>, asli Gmail password nahi · 4) Database / Gmail password badlein to Vercel mein bhi badal kar Redeploy karein.</div>

</body></html>`;

const browser = await chromium.launch({ executablePath: BROWSER });
const page = await browser.newPage();
await page.setContent(html, { waitUntil: 'load' });
await page.pdf({
  path: OUT,
  format: 'A4',
  printBackground: true,
  displayHeaderFooter: true,
  headerTemplate: '<div style="font-size:8px;width:100%;padding:0 14mm;color:#94a3b8;font-family:Segoe UI,sans-serif;">Enterprise ERP — CRUDs aur ENV guide</div>',
  footerTemplate: '<div style="font-size:8px;width:100%;padding:0 14mm;color:#94a3b8;font-family:Segoe UI,sans-serif;text-align:right;">Page <span class="pageNumber"></span> / <span class="totalPages"></span></div>',
  margin: { top: '16mm', bottom: '14mm', left: '0', right: '0' },
});
await browser.close();
console.log('PDF:', OUT);
