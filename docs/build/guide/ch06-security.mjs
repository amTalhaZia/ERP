import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, security, table, checklist } from './lib.mjs';

export default {
  id: 'ch-security',
  title: 'Security & access control',
  subtitle: 'System aap ke data ki hifazat kaise karta hai — aasaan zaban mein — aur production checklist.',
  render: () => `
${h2('sec-server', '6.1 Asal hifazat server par: "button chhupana kaafi nahi"')}
<p class="lead">Jab kisi user ke paas permission nahi hoti to sidebar mein woh menu nazar nahi aata, aur page par woh button (jaise ${ui('Approve')}) bhi nahi hota. Lekin yeh sirf suhulat ke liye hai. <b>Asal faisla har request par server (API) karta hai.</b></p>
${bullets([
  'Agar koi chalak user URL khud type kar le, ya browser tools se request bhej de, tab bhi server permission check karta hai aur inkaar kar deta hai.',
  'Har API action par policy check aur route par permission check hota hai — dono jagah.',
  'Approve, payroll process, user manage, export, stock adjust aur journal posting jaise sensitive kaam audit log mein bhi likhe jate hain.',
])}
<p>Neeche employee ka screenshot hai jis ne direct ${c('/finance/invoices')} URL khola. Sidebar mein Receivables menu tha hi nahi, aur server ne data dene se inkaar kar diya:</p>
${fig('employee-forbidden-invoices', 'Employee ne /finance/invoices khola — server ka jawab: "You don\'t have permission to perform this action." (auth.forbidden). Koi invoice data nahi dikhaya gaya.')}

${h2('sec-403', '6.2 403 aur 404 ka farq')}
${table(['Soorat', 'Server ka jawab', 'Screen par'], [
  ['Aap is company ke member hain lekin permission nahi', '<b>403 Forbidden</b>', `"Something went wrong — You don't have permission to perform this action." (${c('auth.forbidden')})`],
  ['Kisi <b>dusri company</b> ke record ka ID try kiya', '<b>404 Not found</b>', '"Page not found" / record nahi milta'],
  [`Platform pages (${c('/platform/…')}) bina platform role ke`,'<b>404</b>', 'Page mojood hi nahi lagta'],
  ['Session khatam / login nahi', '<b>401</b>', 'Login page par wapas'],
  ['Bohat zyada login attempts', '<b>429</b>', '"Too many attempts" — thori der baad try karein'],
], { caption: 'Error codes ka matlab' })}
<p><b>404 kyun?</b> Agar dusri company ke record par "permission nahi" (403) aaye to is se pata chal jata ke woh record mojood hai. 404 dene se kisi ko yeh bhi maloom nahi hota ke woh ID kisi aur company mein hai ya nahi.</p>

${h2('sec-tenant', '6.3 Company data isolation')}
${bullets([
  `Har request ke sath current company bheji jati hai, aur server check karta hai ke aap us company ke <b>active member</b> hain. Warna ${c('tenant.forbidden')}.`,
  'Database ki har company-wise table mein company ka ID hota hai, aur har query khud-ba-khud sirf current company tak mehdood hoti hai.',
  'Form mein jo bhi related ID aaye (customer, warehouse, account…) server check karta hai ke woh isi company ki hai — client par bharosa nahi.',
  'Automated tests yeh confirm karte hain ke Company A, Company B ka data read, update, delete ya export nahi kar sakti.',
  'Platform staff bhi company data tab tak nahi dekh sakte jab tak member na hon.',
])}

${h2('sec-login', '6.4 Login protections')}
${table(['Control', 'Kaise kaam karta hai'], [
  ['Password policy', 'Kam az kam 10 characters, letters + numbers, aur known leaked passwords reject. Passwords bcrypt hash mein store hote hain.'],
  ['Rate limiting', 'Aik email + IP se <b>5 login attempts/minute</b>, aik IP se 30/minute; 2FA codes 5/minute; forgot/reset 10/minute.'],
  ['Aik jaisa error message', 'Ghalat email ho ya ghalat password — message aik jaisa, taake koi emails "guess" na kar sake. Forgot-password hamesha same jawab deta hai.'],
  ['Two-factor (TOTP)', 'Authenticator app code + single-use recovery codes; secret encrypted store hota hai.'],
  ['Session expiry', `Session cookie HttpOnly hai (JavaScript nahi parh sakta). ${c('SESSION_LIFETIME')} (default 120 minute) idle rehne par logout.`],
  ['Password change', '"Changing your password signs out every other session." — dusre devices se khud logout.'],
  ['Re-confirmation', '2FA on karne jaise sensitive kaam se pehle password dobara (15 minute ke liye valid).'],
  ['Deactivation', 'Inactive user agli request par hi logout ho jata hai.'],
  ['CSRF / CORS', 'Har badalne wali request par CSRF token; API sirf allowed frontend origin se baat karti hai.'],
], { caption: 'Sign-in ki hifazat' })}
${security('Agar kisi ka password leak hone ka shak ho: (1) us user se password change karwayein (dusre sessions khud band), (2) 2FA on karwayein, (3) Audit log mein "Sign-in &amp; security" filter laga kar dekhein ke kahan se login hue.')}

${h2('sec-audit', '6.5 Audit log ka istemal')}
<p>${nav('Insights', 'Audit log')} aik <b>append-only</b> record hai: "Entries cannot be edited or deleted." Is mein security events, settings changes, postings aur exports — kis ne, kab, kis IP se, kya badla — sab likha hota hai. Passwords aur secrets automatically chhupa (redact) diye jate hain.</p>
${steps([
  `Sidebar mein ${ui('Audit log')} kholein (permission: ${c('audit.view')} — Owner, Admin, Auditor).`,
  `Category filter se chunein: ${ui('Sign-in & security')}, ${ui('Users')}, ${ui('Roles & permissions')}, ${ui('Company settings')}, ${ui('Employees')}, ${ui('Financial postings')}, ${ui('Inventory')}, ${ui('Exports')}.`,
  `Table mein ${ui('WHEN')}, ${ui('ACTOR')}, ${ui('ACTION')}, ${ui('RECORD')}, ${ui('IP')} columns hain.`,
  'Kisi row par click karein — drawer mein poori detail aur purani/nayi values ka farq (diff) nazar aata hai.',
])}
${fig('owner-audit-log', 'Audit log — har action ka waqt, actor, action code, record aur IP. Upar wali entries mein employee ki denied attempts (auth.permission_denied) bhi nazar aa rahi hain.')}
${fig('owner-audit-entry-drawer', 'Audit entry drawer — aik entry ki mukammal detail.')}
${tip(`Mashkook activity dhoondne ke liye: ${c('auth.permission_denied')} (koi baar baar mana kiye gaye pages khol raha hai), failed logins, aur raat ke waqt exports dekhein. Audit log default <b>730 din</b> rakha jata hai (${c('ERP_RETAIN_AUDIT_DAYS')}).`)}

${h2('sec-checklist', '6.6 Production security checklist')}
<p>Live (production) jane se pehle IT person har point tick kare:</p>
${checklist([
  `${c('APP_ENV=production')}, ${c('APP_DEBUG=false')}, unique ${c('APP_KEY')}`,
  `HTTPS har jagah; ${c('SESSION_SECURE_COOKIE=true')}; proxy par HSTS`,
  `${c('CORS_ALLOWED_ORIGINS')} / ${c('SANCTUM_STATEFUL_DOMAINS')} / ${c('SESSION_DOMAIN')} asli domains par set`,
  `Runtime database user ke paas ${c('DROP')}/${c('ALTER')} nahi; migrations ke liye alag user`,
  `Runtime user se audit log ki editing revoke: ${c("REVOKE UPDATE, DELETE ON erp.audit_logs FROM 'erp_app'@'%';")}`,
  `Hosted DB: TLS CA configured (${c('DB_SSL_CA')}) aur IP allow-list restricted`,
  'Mail transport configured (password reset, invitations)',
  'Queue worker aur scheduler supervisor ke under chal rahe hain',
  `${c('php artisan config:cache route:cache event:cache')}`,
  'Raat ko encrypted backups, aur restore test kiya hua (Chapter 3.4)',
  `${c('composer audit')} aur ${c('npm audit --omit=dev')} saaf ya review kiye hue`,
  `Pehla admin ${c('erp:create-admin')} se bana, 2FA on, ${c('ERP_ALLOW_ADMIN_BOOTSTRAP')} wapas ${c('false')}`,
  `Demo data mojood nahi (kisi company par ${c('is_demo')} nahi)`,
])}
${warn('Security masle (vulnerability) ka pata chale to system owner ko <b>private</b> taur par batayein — public jagah post na karein.')}
`,
};
