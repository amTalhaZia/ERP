import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, security, pre } from '../guide/lib.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const factsFile = path.join(HERE, '..', 'screenshots-access', '_facts.json');
const facts = fs.existsSync(factsFile) ? JSON.parse(fs.readFileSync(factsFile, 'utf8')) : {};
const excerpt = facts.resetLogExcerpt
  ? facts.resetLogExcerpt.replace(/\r/g, '').split('\n')
    .filter((l) => l.trim() && !l.trim().startsWith('<') && !l.includes('trouble clicking') && !l.startsWith('into your web browser'))
    .slice(0, 8).join('\n')
  : `[2026-10-16 …] local.DEBUG: From: Enterprise ERP <no-reply@example.com>
To: guide.owner@guidedemo.test
Subject: Reset Password Notification
…
Reset Password: http://localhost:3000/reset-password?token=<redacted>&email=guide.owner%40guidedemo.test`;

export default {
  id: 'acc-level1',
  title: 'Platform Owner → Company + Company Owner',
  subtitle: 'Nayi company banana, owner ko invitation, owner ka password set karna aur Platform Admin.',
  render: () => `
${h2('l1-create', '3.1 Nayi company aur us ka owner banana')}
<p class="lead">Platform Owner ka sab se aham kaam: nayi company "provision" karna. Is ke sath hi company ka pehla shakhs — <b>Company Owner</b> — invite hota hai.</p>
${steps([
  `Sidebar mein ${ui('Companies')} <span class="badge-n">1</span> kholein, upar right ${ui('New company')} <span class="badge-n">2</span> dabayein.`,
])}
${fig('a02-platform-companies', 'Platform → Companies — (1) Companies menu, (2) New company button.')}
${steps([
  `"Provision a company" drawer mein ${ui('Name')} <span class="badge-n">1</span> likhein (yahan "Guide Demo Co (Demo)"), phir ${ui('Legal name')}, ${ui('Email')}, ${ui('Country')} (ISO code, maslan PK), ${ui('Base currency')} (PKR) aur ${ui('Time zone')} (Asia/Karachi).`,
  `${ui('Demo company')} <span class="badge-n">2</span> — sirf training/testing company ke liye on karein. Asli company ke liye <b>off</b> rakhein.`,
])}
${fig('a03-new-company-details', 'Company section — (1) Name, (2) Demo company switch (sirf is fictional company ke liye on).')}
${steps([
  `Neeche <b>Owner</b> section: ${ui('Owner name')} <span class="badge-n">3</span> aur ${ui('Owner email')} <span class="badge-n">4</span>. Hint: "Existing users are added as owner; new users receive an email to set their password."`,
  `${ui('Create company')} <span class="badge-n">5</span> dabayein.`,
])}
${fig('a04-new-company-owner', 'Owner section — (3) Owner name, (4) Owner email, (5) Create company.')}
<p>System khud: company, aik <b>head office</b> branch, <b>10 standard roles</b>, <b>chart of accounts</b> aur defaults banata hai, owner ko <b>Company Owner</b> role deta hai, aur email bhejta hai.</p>
${fig('a05-company-created', 'Nayi company list mein — "Guide Demo Co (Demo)" Demo badge ke sath.')}

${h2('l1-email', '3.2 Owner ko email — aur development mein link kahan milta hai')}
<p>Naye owner ko do emails jati hain: (1) "You've been added to Guide Demo Co (Demo)" — "An account was created for you… Check your inbox for a link to set your password, then sign in." aur (2) <b>Reset Password</b> email jis mein password set karne ka link hota hai. Kisi email mein password nahi hota.</p>
<p>Development mein ${c('MAIL_MAILER=log')} hota hai — email bheji nahi jati, balke ${c('backend\\storage\\logs\\laravel.log')} file ke aakhir mein likhi jati hai. File Notepad/VS Code mein kholein aur neeche se ${c('reset-password')} search karein:</p>
${pre(excerpt, 'backend\\storage\\logs\\laravel.log (token yahan chhupaya gaya hai)')}
${warn('Yeh link aik <b>secret</b> hai — jis ke paas link ho woh password set kar sakta hai. Link sirf us shakhs ko dein jis ke liye hai, aur production mein asli mail server (MAIL_*) configure karein taake link seedha owner ke inbox mein jaye. Link kuch der baad expire ho jata hai; phir login page ka "Forgot password?" use karein.')}

${h2('l1-reset', '3.3 Owner apna password set karta hai')}
${steps([
  'Owner email ka link kholta hai — "Choose a new password" page, upar us ka email likha hota hai.',
  `${ui('New password')} <span class="badge-n">1</span> aur ${ui('Confirm password')} <span class="badge-n">2</span> — kam az kam 10 characters, letters + numbers, aur koi leaked password nahi.`,
  `${ui('Update password')} <span class="badge-n">3</span> dabata hai. "Other active sessions will be signed out."`,
])}
${fig('a10-reset-password-filled', 'Reset password page — (1) New password, (2) Confirm password, (3) Update password.')}
${fig('a11-reset-password-done', 'Update password dabane ke baad — password save ho raha hai; is ke baad owner login page se sign in karta hai.')}
${steps([
  'Owner login karta hai — us ki nayi company ka khaali dashboard (currency PKR) aur poora sidebar nazar aata hai.',
  'Sab se pehle: Account menu → Security → two-factor on (chapter 2.2 jaisa).',
  `${nav('Users & roles', 'Roles & permissions')} mein 10 tayyar roles maujood hain.`,
])}
${fig('a12-guide-owner-dashboard', 'Guide Owner ka pehla login — nayi company "Guide Demo Co (Demo)", sab KPIs zero, poora menu.')}
${fig('a13-guide-owner-roles', 'Nayi company ke 10 standard roles — Company Owner "Locked", baqi "System".')}

${h2('l1-platform-admin', '3.4 Platform Admin banana (sirf Platform Owner)')}
<p>Agar Platform Owner ko madadgar chahiye jo companies aur accounts manage kare, to woh kisi account ko <b>Platform Admin</b> bana sakta hai:</p>
${steps([
  `${ui('Platform users')} kholein — har account ki platform role, companies ki tadaad, 2FA aur last sign-in nazar aata hai.`,
  'Us user ki row ke aakhir mein ⋯ (Row actions) dabayein.',
  `${ui('Make … a platform admin')} <span class="badge-n">1</span> chunein — confirm dialog aata hai.`,
  'Confirm karein. (Is guide mein demo ke liye dialog <b>cancel</b> kiya gaya — kisi ko platform role nahi diya gaya.)',
])}
${fig('a06-platform-users', 'Platform users — tamam accounts, platform role, 2FA status.')}
${fig('a07-platform-make-admin-menu', 'Row actions menu — (1) platform admin banane ka option; "Deactivate account" bhi yahin hai.')}
${fig('a08-platform-make-admin-confirm', 'Confirm dialog — Platform admins poore platform par companies aur users manage kar sakte hain.')}
${note('Sirf <b>Platform Owner</b> platform roles de ya hata sakta hai ("Only a platform owner can change platform roles"), aur koi apni platform role khud nahi badal sakta.')}
${security('Platform staff (Owner/Admin) kisi company ka business data — employees, salaries, invoices — <b>nahi</b> dekh sakte jab tak woh us company ke member na hon. Platform panel sirf companies aur accounts ka intezam hai.')}
${tip('Agar Owner email ghalat likh di gayi ho to company ke Users page se sahi shakhs ko Company Owner role ke sath invite karein, phir ghalat account remove karein.')}
`,
};
