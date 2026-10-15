import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, security, table, pre } from './lib.mjs';

export default {
  id: 'ch-first-admin',
  title: 'Pehla Admin account banana',
  subtitle: 'Platform owner, two-factor authentication, pehli company aur us ka owner.',
  render: () => `
${h2('admin-why', '4.1 Koi default ya "backdoor" account kyun nahi?')}
<p class="lead">Bohat se software ${c('admin / admin123')} jaisa default account ke sath aate hain — aur hackers sab se pehle yahi try karte hain. Is ERP mein <b>koi default username/password nahi</b>, koi chhupa hua master password nahi, aur koi "backdoor" nahi. Pehla administrator aap khud, server par aik command chala kar, apne email aur mazboot password ke sath banate hain.</p>
${table(['Account type', 'Kaun', 'Kya kar sakta hai'], [
  ['<b>Platform owner</b>', 'Poori installation ka malik (aam taur par IT head / software owner)', 'Companies banana/band karna, platform users, system health. Kisi company ka business data <b>nahi</b> dekh sakta jab tak member na ho.'],
  ['<b>Platform admin</b>', 'Platform owner ka madadgar', 'Platform panel ka kaam; platform roles sirf owner de sakta hai.'],
  ['<b>Company Owner</b>', 'Har company ka business owner', 'Us company ke andar sab kuch (Chapter 5).'],
], { caption: 'Admin accounts ki qismein' })}

${h2('admin-create-dev', '4.2 erp:create-admin — development mein')}
<p>Backend folder mein command chalayein aur sawalon ke jawab dein:</p>
${pre(`PS> cd backend
PS> php artisan erp:create-admin

 Full name:
 > Adnan Ahmed

 Email:
 > adnan@yourcompany.com

 Password (min 10 chars, letters + numbers):
 >   (type karte waqt nazar nahi aayega)

 INFO  Platform owner created: adnan@yourcompany.com
 WARN  Enable two-factor authentication for this account after first login.`, 'Interactive steps')}
${steps([
  `${c('Full name')} — poora naam.`,
  `${c('Email')} — asli, aap ka apna email (password reset isi par aayega). Pehle se mojood email accept nahi hota.`,
  `${c('Password')} — kam az kam <b>10 characters</b>, letters <b>aur</b> numbers dono. Password ko known data-breach lists se bhi check kiya jata hai; leaked password reject ho jayega.`,
  'Kamyabi par "Platform owner created" message aata hai. Yeh event audit log mein bhi record hota hai.',
])}
${tip(`Development mein ${c('--name')}, ${c('--email')}, ${c('--password')} options bhi diye ja sakte hain. Production mein ${c('--password')} <b>refuse</b> hota hai, kyun ke command line ka text shell history mein save ho jata hai.`)}

${h2('admin-create-prod', '4.3 Production mein pehla admin')}
<p>Production mein yeh command ghalti se na chal jaye, is liye do tala (locks) hain: environment flag aur ${c('--force')}. Aur agar platform owner pehle se mojood ho to command chalti hi nahi.</p>
${steps([
  `${c('backend/.env')} mein ${c('ERP_ALLOW_ADMIN_BOOTSTRAP=true')} set karein. (Agar ${c('config:cache')} use karte hain to ${c('php artisan config:cache')} dobara chalayein.)`,
  `Command chalayein: ${c('php artisan erp:create-admin --force')} — naam, email aur password <b>prompt</b> par dein.`,
  `Foran ${c('ERP_ALLOW_ADMIN_BOOTSTRAP=false')} wapas karein (aur config cache dobara banayein).`,
  'Login karke turant two-factor authentication on karein (4.4).',
  'Mazeed platform admins ab command se nahi, balke Platform panel se manage hote hain.',
])}
${pre(`# Linux server (one-off, env var inline):
ERP_ALLOW_ADMIN_BOOTSTRAP=true php artisan erp:create-admin --force

# Agar flag/force na ho:
 ERROR  In production set ERP_ALLOW_ADMIN_BOOTSTRAP=true and pass --force.
# Agar owner pehle se ho:
 ERROR  A platform owner already exists. Manage further admins from the platform panel.`, 'Production')}
${security(`${c('ERP_ALLOW_ADMIN_BOOTSTRAP')} ko <b>true</b> chhor dena production security checklist ki failure hai. Kaam hote hi false karein.`)}

${h2('admin-2fa', '4.4 Two-factor authentication (2FA) on karna')}
<p>2FA ka matlab: login ke waqt password ke ilawa phone ki authenticator app (Google Authenticator, Microsoft Authenticator, 1Password…) ka 6-digit code bhi chahiye. Password chori ho jaye tab bhi account mehfooz rehta hai. <b>Har admin aur owner ke liye lazmi samjhein.</b></p>
${fig('public-login', 'Login page — email aur password. Access sirf invitation se milta hai; khud sign-up ka option nahi.')}
${steps([
  `Apne email/password se login karein. Upar right mein apne initials (Account menu) par click karein → ${ui('Security')}. (Ya seedha ${c('/settings/security')}.)`,
  `${ui('Two-factor authentication')} card mein status ${ui('Off')} hoga. ${ui('Set up two-factor')} par click karein.`,
  'Security ke liye system dobara aap ka password maang sakta hai (password confirmation, 15 minute tak valid).',
  `Screen par QR code aata hai: "1. Scan the QR code with your authenticator app, or enter this key manually". Phone ki app se scan karein.`,
  `"2. Enter the 6-digit code it shows" — app ka code ${ui('Verification code')} mein likh kar ${ui('Verify & enable')} dabayein.`,
  `${ui('Save your recovery codes')} — recovery codes dikhaye jate hain. ${ui('Copy codes')} se copy karke kisi mehfooz jagah (password manager / taala band file) rakhein. Har code sirf aik dafa chalta hai; phone gum ho jaye to inhi se login hoga.`,
])}
${fig('owner-settings-security', 'Settings → Security: password badalna aur two-factor authentication. Status "Off" hai — Set up two-factor dabayein.')}
${note(`Baad mein ${ui('Regenerate recovery codes')} se naye codes bana sakte hain, aur 2FA band karne ke liye ${ui('Password to disable')} mein password de kar ${ui('Disable')}.`)}

${h2('admin-company', '4.5 Platform panel: nayi company aur us ka owner')}
<p>Platform owner login karta hai to sidebar mein sirf ${ui('Platform')} section hota hai: ${ui('Companies')}, ${ui('Platform users')}, ${ui('System')}.</p>
${fig('platform-companies', 'Platform → Companies: installation ki saari companies, demo badge, users aur branches ki tadaad.')}
${steps([
  `Sidebar mein ${nav('Platform', 'Companies')} kholein aur upar right ${ui('New company')} dabayein.`,
  `Drawer mein company ki details bharein: ${ui('Name')}, ${ui('Legal name')}, ${ui('Email')}, ${ui('Country')} (ISO code, jaise PK, US), ${ui('Base currency')}, ${ui('Time zone')}.`,
  `${ui('Owner name')} aur ${ui('Owner email')} — yeh shakhs company ka <b>Company Owner</b> banega.`,
  `${ui('Demo company')} switch sirf testing ke liye on karein ("Clearly labelled as demo data throughout the app"). Asli company ke liye off rakhein.`,
  'Save karein. System khud-ba-khud company, aik <b>head office</b> branch, standard <b>10 roles</b>, <b>chart of accounts</b> aur defaults bana deta hai, aur owner ko invite karta hai.',
])}
${fig('platform-new-company-drawer', 'New company drawer — company details aur owner ka naam/email. "Creates the company, a head office, the standard roles, chart of accounts and defaults, and invites the owner."')}
${h3('Owner ko kya milta hai?')}
${bullets([
  `<b>Naya user</b> ho to: email "You've been added to &lt;Company&gt;" — "An account was created for you… Check your inbox for a link to set your password, then sign in." Sath hi aik alag <b>password-set (reset) email</b> aati hai jis ke link se owner apna password khud chunta hai. Kisi email mein password nahi bheja jata.`,
  `<b>Pehle se mojood user</b> ho to: usay company mein owner ke taur par add kar diya jata hai aur email mein ${ui('Sign in')} link hota hai; login ke baad company switcher se nayi company chunein.`,
  `Development mein (${c('MAIL_MAILER=log')}) yeh emails ${c('backend\\storage\\logs\\laravel.log')} mein milti hain.`,
])}
${fig('public-forgot-password', 'Forgot password page — agar link expire ho jaye to owner yahan se naya password-reset link mangwa sakta hai.')}
${h3('Platform users')}
<p>${nav('Platform', 'Platform users')} mein installation ke tamam accounts, un ki platform role, companies ki tadaad, 2FA status aur last sign-in nazar aate hain. Platform roles sirf platform owner de sakta hai, aur koi apni platform role khud nahi badal sakta.</p>
${fig('platform-users', 'Platform users — har account ka 2FA status aur last sign-in; yahan se dekh sakte hain kis ne 2FA on nahi kiya.')}
${note(`Platform owner jab ${c('/dashboard')} kholta hai to "Dashboard unavailable — Your role does not include access to the executive dashboard" nazar aata hai. Yeh normal hai: platform staff company ka business data nahi dekhte. Seedha ${nav('Platform', 'Companies')} use karein.`)}

${h2('admin-demo', '4.6 Demo data (sirf testing ke liye)')}
<p>Training ya testing ke liye do tayyar demo companies load ki ja sakti hain — <b>Northwind Trading (Demo)</b> (USD) aur <b>Indus Manufacturing (Demo)</b> (PKR) — jin mein chhe mahine ki consistent history hai: employees, attendance, leave, payroll runs, products/stock, suppliers/POs, customers, quotations, orders, invoices, journals, projects aur documents.</p>
${pre(`php artisan db:seed --class=DemoSeeder`, 'backend (development only)')}
${bullets([
  `Demo users ka password ${c('DEMO_PASSWORD')} environment variable se set hota hai; set na ho to random password ban kar print hota hai.`,
  `Demo companies ${c('is_demo')} flag ke sath hoti hain aur app mein har jagah "Demo" badge nazar aata hai.`,
  'Seeder production environment mein chalne se <b>inkaar</b> kar deta hai.',
])}
${warn('<b>Demo data kabhi production mein nahi.</b> Demo users ke passwords sab ko maloom hote hain (training material mein likhe hote hain). Production checklist ka aik point yahi hai: kisi company par demo flag na ho.')}
`,
};
