import { h2, h3, ui, nav, c, fig, steps, tip, warn, security, pre } from '../guide/lib.mjs';

export default {
  id: 'acc-level0',
  title: 'Level 0 — Platform Owner (super admin)',
  subtitle: 'Sab se upar wala account kaise banta hai aur pehla login.',
  render: () => `
${h2('l0-create', '2.1 Platform Owner banana (server par, aik dafa)')}
<p class="lead">ERP mein koi default "admin/admin" account nahi. Pehla super admin server par command chala kar banaya jata hai. Backend folder mein:</p>
${pre(`PS C:\\…\\ERP\\backend> php artisan erp:create-admin

 Full name:
 > Adnan Ahmed

 Email:
 > adnan@yourcompany.com

 Password (min 10 chars, letters + numbers):
 >

 INFO  Platform owner created: adnan@yourcompany.com
 WARN  Enable two-factor authentication for this account after first login.`, 'Development')}
${steps([
  'Naam, apna asli email, aur mazboot password dein (kam az kam 10 characters, letters + numbers; leaked passwords reject hote hain).',
  '"Platform owner created" message ka matlab kaam ho gaya; yeh event audit log mein bhi jata hai.',
])}
${h3('Production server par')}
${pre(`# 1) backend/.env:   ERP_ALLOW_ADMIN_BOOTSTRAP=true    (config:cache use karte hain to dobara cache karein)
# 2) command (password prompt par dein, command line par nahi):
php artisan erp:create-admin --force
# 3) foran wapas:     ERP_ALLOW_ADMIN_BOOTSTRAP=false`, 'Production')}
${warn(`Production mein flag ya ${c('--force')} ke baghair command inkaar karti hai, ${c('--password')} option refuse hota hai (shell history ki wajah se), aur agar Platform Owner pehle se ho to dusra isi tareeqe se nahi banta — "Manage further admins from the platform panel."`)}

${h2('l0-login', '2.2 Pehla login aur 2FA')}
${steps([
  `Browser mein ERP kholein, apne email/password se ${ui('Sign in')} karein.`,
  `Platform Owner ka sidebar sirf ${ui('Platform')} section dikhata hai: ${ui('Companies')}, ${ui('Platform users')}, ${ui('System')}. Woh kisi company ka business data nahi dekhta.`,
  `Upar right Account menu → ${ui('Security')} kholein aur ${ui('Set up two-factor')} <span class="badge-n">1</span> dabayein.`,
  'Password dobara confirm karein, phone ki authenticator app se QR scan karein, 6-digit code daal kar Verify &amp; enable, aur recovery codes mehfooz rakhein.',
])}
${fig('a01-platform-security-2fa', 'Platform Owner → Security: Two-factor authentication "Off" — Set up two-factor (1).')}
${security('Platform Owner poori installation ki chaabi hai. Is account par 2FA <b>lazmi</b> on karein aur password kisi ke sath share na karein.')}
${tip('Platform Owner jab Dashboard kholta hai to "Dashboard unavailable" aata hai — yeh normal hai, kyun ke platform staff company data nahi dekhte.')}
`,
};
