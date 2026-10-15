import { h2, ui, nav, c, fig, steps, bullets, tip, note, warn, table } from '../guide/lib.mjs';

export default {
  id: 'acc-level2',
  title: 'Company Owner → Company Administrator',
  subtitle: 'Roz ke admin kaam ke liye "chhota admin" banana.',
  render: () => `
${h2('l2-why', '4.1 Company Administrator kyun?')}
<p class="lead">Company Owner ke paas har cheez ka ikhtiyar hai aur us ka role <b>locked</b> hai. Roz-marrah ka kaam — users add karna, roles theek karna, settings — aik <b>Company Administrator</b> ko dein, taake Owner ka login kam se kam istemal ho.</p>
${table(['Company Administrator', ''], [
  ['<b>Kar sakta hai</b>', 'Users invite/edit/deactivate/remove, roles aur permissions manage, company settings &amp; branding, branches, departments, aur tamam modules (HR, finance, sales, inventory, projects…), audit log, reports export.'],
  ['<b>Nahi kar sakta</b>', '"Run &amp; approve payroll" (payroll.process) — default role mein shamil nahi. Company Owner role edit nahi kar sakta (locked). Apni membership edit/deactivate/remove nahi kar sakta.'],
], { caption: 'config/erp.php: "Manages users, settings and all modules except payroll processing." — 88 permissions' })}

${h2('l2-invite', '4.2 Invite karna — step by step')}
${steps([
  `Company Owner (yahan ${c('owner@northwind.test')}) sidebar mein ${nav('Users & roles', 'Users')} <span class="badge-n">1</span> kholta hai.`,
  `Upar right ${ui('Invite user')} <span class="badge-n">2</span> dabata hai.`,
])}
${fig('a14-owner-users-invite-button', 'Users page — (1) Users menu, (2) Invite user.')}
${steps([
  `"Invite a user" drawer: ${ui('Full name')} <span class="badge-n">1</span> aur ${ui('Work email')} <span class="badge-n">2</span>.`,
  `${ui('Roles')} mein ${ui('Company Administrator')} <span class="badge-n">3</span> tick karein.`,
  `${ui('Access to all branches')} <span class="badge-n">4</span> on rehne dein — admin ko poori company chahiye.`,
  `${ui('Send invitation')} <span class="badge-n">5</span>.`,
])}
${fig('a15-invite-sub-admin', 'Invite drawer — (1) naam, (2) email, (3) Company Administrator role, (4) Access to all branches, (5) Send invitation.')}
${fig('a16-sub-admin-in-list', 'Naya admin list mein (neeche laal box) — role "Company Administrator", Branches "All", Last sign-in ki jagah "Invite pending"; "User created" toast. Pehle login tak row menu mein "Resend invitation" bhi hota hai.')}
<p>Naye shakhs ko company ka invitation email aur password-set link milta hai (chapter 3.2 jaisa). Woh password set karke login karta hai.</p>
${note(`Is guide mein development ki suhulat ke liye invited demo users ka password server par seedha set kiya gaya (${c('php artisan tinker')}). Asli users hamesha emailed link se khud password chunte hain — admin ko kabhi kisi ka password maloom nahi hona chahiye.`)}

${h2('l2-sees', '4.3 Admin ko kya nazar aata hai')}
${fig('a17-sub-admin-dashboard', 'sub.admin@northwind.test ka dashboard — Owner jaisa poora menu (Users &amp; roles samet).')}
${warn(`<b>Ahem — admin ko taqreeban Owner jitna bharosemand samjhein.</b> Company Administrator ke paas "Manage roles &amp; permissions" aur "Invite users" hai. Is version mein system usay yeh karne se nahi rokta: (a) kisi naye user ko ${ui('Company Owner')} role dena, ya (b) ${ui('Company Administrator')} system role mein khud "Run &amp; approve payroll" permission add kar dena. Yani "payroll processing ke siwa" wali hadd tooti ja sakti hai. Is liye admin sirf bharosemand shakhs ko banayein, aur Audit log (Roles &amp; permissions, Users) par nazar rakhein.`)}
${tip('Kam log hon to bhi Owner aur Admin alag accounts rakhein — Owner ka password aur 2FA mehfooz jagah, roz ka kaam Admin se.')}
`,
};
