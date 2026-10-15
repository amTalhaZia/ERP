import { h2, ui, nav, c, bullets, tip, note, table, security } from '../guide/lib.mjs';

export default {
  id: 'acc-hierarchy',
  title: 'Admin hierarchy — kaun kis ko access deta hai',
  subtitle: 'Poore system ki "seedhi": sab se upar Platform Owner, neeche company ke log.',
  render: () => `
${h2('h-why', '1.1 Is guide ka maqsad')}
<p class="lead">Is guide mein hum aik <b>asli misaal</b> step-by-step chalayenge: sab se upar wala admin (Platform Owner) aik nayi company aur us ka owner banata hai; Company Owner apne liye aik "chhota admin" (Company Administrator) banata hai; woh admin branch-wise managers banata hai aur zaroorat par custom role. Aakhir mein dekhenge ke access wapas kaise liya jata hai aur sab kuch audit log mein kaise nazar aata hai.</p>
<p>Har screenshot mein jis button ya field par click karna hai, us ke gird <span class="mark-legend">laal (red) box</span> hai, aur jahan kai steps hain wahan <span class="badge-n">1</span> <span class="badge-n">2</span> <span class="badge-n">3</span> jaise numbers — wohi order follow karein.</p>
${note(`Screenshots development database ke hain. Is guide ke liye <b>fictional</b> demo log aur company banayi gayi: ${c('Guide Demo Co (Demo)')}, ${c('sub.admin@northwind.test')}, ${c('branch.hr@northwind.test')} waghera. Asli company mein aap apne logon ke naam aur emails use karenge.`)}

${h2('h-levels', '1.2 Levels (seedhi)')}
<div class="tree">
  <div class="tag">Level 0 — poori installation</div>
  <div class="lvl"><div class="node l0"><b>Platform Owner</b><span>erp:create-admin se banta hai<br>companies banata hai</span></div><div class="node side"><b>Platform Admin</b><span>sirf Platform Owner bana sakta hai</span></div></div>
  <div class="link"></div>
  <div class="tag">Level 1 — har company ka malik</div>
  <div class="lvl"><div class="node l1"><b>Company Owner</b><span>company banate waqt invite hota hai<br>sab permissions (locked role)</span></div></div>
  <div class="link"></div>
  <div class="tag">Level 2 — "chhota admin"</div>
  <div class="lvl"><div class="node l2"><b>Company Administrator</b><span>users, roles, settings, sab modules<br>siwaye payroll processing</span></div></div>
  <div class="link"></div>
  <div class="tag">Level 3 — department heads (branch-wise bhi)</div>
  <div class="lvl">
    <div class="node"><b>HR Manager</b><span>HR + payroll</span></div>
    <div class="node"><b>Accountant</b><span>finance</span></div>
    <div class="node"><b>Sales Manager</b><span>CRM + sales</span></div>
    <div class="node"><b>Inventory Mgr</b><span>stock</span></div>
    <div class="node"><b>Procurement</b><span>purchasing</span></div>
    <div class="node"><b>Dept Manager</b><span>approvals, projects</span></div>
  </div>
  <div class="link"></div>
  <div class="tag">Level 4 — staff</div>
  <div class="lvl"><div class="node"><b>Employee</b><span>self-service</span></div><div class="node side"><b>Read-Only Auditor</b><span>sab dekhe, kuch na badle</span></div><div class="node side"><b>Custom roles</b><span>aap ke banaye hue</span></div></div>
</div>

${h2('h-who', '1.3 Kaun kis ko bana sakta hai')}
${table(['Kaun', 'Kya bana / de sakta hai', 'Kahan se'], [
  ['<b>Server ka IT person</b>', 'Sirf pehla Platform Owner (aik dafa)', c('php artisan erp:create-admin')],
  ['<b>Platform Owner</b>', 'Nayi company + us ka Company Owner; Platform Admin banana/hatana; kisi bhi account ko poore platform par deactivate', `${nav('Platform', 'Companies')}, ${nav('Platform', 'Platform users')}`],
  ['<b>Platform Admin</b>', 'Companies aur accounts manage; platform roles <b>nahi</b> de sakta', 'Platform panel'],
  ['<b>Company Owner</b>', 'Company mein koi bhi user, koi bhi role (Company Owner samet); roles edit; custom roles', `${nav('Users & roles', 'Users')} / ${ui('Roles & permissions')}`],
  ['<b>Company Administrator</b>', 'Users invite/edit/remove, roles edit, custom roles — kyun ke us ke paas "Invite, update, deactivate users" aur "Manage roles &amp; permissions" hai', 'Wohi pages'],
  ['<b>Baqi roles</b>', 'Kisi ko access nahi de sakte (users.manage / roles.manage nahi)', '—'],
], { caption: 'Access dene ka ikhtiyar' })}
${security('Access dene ka ikhtiyar bhi aik permission hai: "Invite, update, deactivate users" (users.manage) aur "Manage roles &amp; permissions" (roles.manage). Jis role ko yeh do permissions dein, woh admin ban jata hai — is liye yeh sirf bharosemand logon ko dein.')}

${h2('h-rules', '1.4 Teen bunyadi qawaid')}
${bullets([
  '<b>Access roles se aati hai</b> — user ki permissions us ke tamam roles ka majmooa (union) hain, aur yeh <b>har company mein alag</b> hain.',
  '<b>Branch restriction</b> — user ko sirf kuch branches tak rakha ja sakta hai; phir woh dusri branches ka branch-wise data nahi dekhta.',
  '<b>Server faisla karta hai</b> — menu chhupana sirf suhulat hai; koi URL khud type kare tab bhi server permission check karta hai.',
])}
${tip('Aam usool: <b>Owner kam, Admin chand, baqi sab apne kaam ke role mein.</b> Har shakhs ka apna login ho — shared accounts nahi.')}
`,
};
