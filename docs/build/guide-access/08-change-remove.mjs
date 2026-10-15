import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, table } from '../guide/lib.mjs';

export default {
  id: 'acc-change',
  title: 'Access badalna, rokna aur hatana',
  subtitle: 'Role change, membership deactivate, company se remove, platform-level deactivate — aur audit log.',
  render: () => `
${h2('ch-options', '8.1 Chaar tareeqe — kab kaunsa')}
${table(['Tareeqa', 'Kahan', 'Asar', 'Kab use karein'], [
  [`<b>Role badalna</b>`, `${nav('Users & roles', 'Users')} → ⋯ → ${ui('Edit access')}`, 'Agli request se nayi permissions', 'Promotion, department change'],
  [`<b>Membership inactive</b>`, `${ui('Edit access')} → ${ui('Active in this company')} off`, '"Inactive members cannot sign in to this company." Record aur roles baqi', 'Lambi chhutti, suspension, investigation'],
  [`<b>Remove from company</b>`, `⋯ → ${ui('Remove from company')}`, '"They lose access to this company immediately." Doosri companies par asar nahi', 'Shakhs company chhor gaya'],
  [`<b>Account deactivate</b> (platform)`, `${nav('Platform', 'Platform users')} → ⋯ → ${ui('Deactivate account')}`, '"They are signed out everywhere and cannot sign in to any company."', 'Poore platform se bahar (sirf platform staff)'],
], { caption: 'Access control ke tareeqe' })}

${h2('ch-role', '8.2 Role badalna')}
${steps([
  `Users list mein us user ki row ke aakhir mein ⋯ (Row actions) <span class="badge-n">1</span> dabayein.`,
  `${ui('Edit access')} <span class="badge-n">2</span> chunein.`,
])}
${fig('a31-row-actions-menu', 'Row actions — (1) ⋯ button, (2) Edit access. Neeche "Remove from company" bhi.')}
${steps([
  `"Access for …" drawer mein roles tick/untick karein — yahan Branch Manager ko ${ui('Employee')} role <span class="badge-n">3</span> bhi diya gaya (dono roles ki permissions mil jati hain).`,
  `Branches bhi yahin badli ja sakti hain. ${ui('Save access')} <span class="badge-n">4</span>.`,
])}
${fig('a32-edit-access-change-role', 'Edit access drawer — (3) naya role, (4) Save access.')}

${h2('ch-deactivate', '8.3 Membership deactivate karna')}
${steps([
  `${ui('Edit access')} kholein, neeche ${ui('Active in this company')} <span class="badge-n">1</span> switch off karein.`,
  `${ui('Save access')} <span class="badge-n">2</span>. List mein status "Inactive" ho jata hai.`,
])}
${fig('a33-deactivate-membership', 'Active in this company (1) off — Save access (2).')}
${fig('a34-membership-inactive-row', 'User ab "Inactive" — roles aur branches record mein baqi hain.')}
<p>Inactive member agar sign in kare to woh is company mein dakhil nahi ho sakta — us ka koi menu nahi hota aur Dashboard "unavailable" dikhata hai. Agar woh pehle se logged-in ho to agli request par hi bahar ho jata hai.</p>
${fig('a35-inactive-member-login', 'Inactive member ka login — koi company/menu nahi, "Dashboard unavailable".')}
${note('Wapas access dene ke liye wohi switch on karke Save access. (Is guide mein demo user ko screenshot ke baad dobara active kar diya gaya.)')}

${h2('ch-remove', '8.4 Company se remove karna aur platform-level deactivate')}
${steps([
  `⋯ → ${ui('Remove from company')} <span class="badge-n">1</span> (laal, destructive option).`,
  `Confirm dialog: "They lose access to this company immediately. Their account and history in other companies are not affected." → ${ui('Remove')}.`,
])}
${fig('a36-remove-menu', 'Row actions — (1) Remove from company.')}
${fig('a37-remove-confirm', 'Remove confirm dialog.')}
${h3('Poore platform se band karna (Platform Owner/Admin)')}
${steps([
  `${nav('Platform', 'Platform users')} → user ki row ⋯ → ${ui('Deactivate account')} <span class="badge-n">1</span>.`,
  'Confirm: "They are signed out everywhere and cannot sign in to any company."',
  `Baad mein wapas: ⋯ → ${ui('Reactivate account')}.`,
])}
${fig('a38-platform-deactivate-menu', 'Platform users — (1) Deactivate account.')}
${fig('a39-platform-deactivate-confirm', 'Deactivate confirm — har jagah se sign out.')}
${fig('a40-platform-deactivated-row', 'Account "Inactive" — (demo ke baad dobara Reactivate kiya gaya).')}
${warn('Aap apne aap ko remove/deactivate nahi kar sakte, aur apna aakhri woh role nahi hata sakte jis se users manage hote hain — taake company baghair admin ke na reh jaye.')}

${h2('ch-forbidden', '8.5 Jab koi bina ijazat page khole')}
<p>Branch HR ke paas finance ki permission nahi — sidebar mein Receivables menu hi nahi. Agar woh URL khud type kare (${c('/finance/invoices')}), server inkaar karta hai aur koi data nahi bhejta:</p>
${fig('a29-branch-hr-forbidden', 'branch.hr@northwind.test ne /finance/invoices khola — "You don\'t have permission to perform this action." (auth.forbidden).')}
${fig('a30-branch-hr-forbidden-users', 'Wohi user /access/users kholta hai — users list bhi forbidden.')}
${tip('Aisi har koshish audit log mein "auth.permission_denied" ke naam se likhi jati hai — baar baar ho to us user se baat karein.')}

${h2('ch-audit', '8.6 Audit log mein sab tabdeeliyan')}
<p>${nav('Insights', 'Audit log')} mein category filter se ${ui('Users')} aur ${ui('Roles & permissions')} chunein. Har invite, membership update, removal aur role permission change — kis ne, kab, kis IP se — nazar aata hai; row par click karne se drawer mein poori detail (purani/nayi values).</p>
${fig('a41-audit-users', 'Audit log → Users category — invitations aur membership updates.')}
${fig('a42-audit-entry-drawer', 'Aik users entry ki detail drawer.')}
${fig('a43-audit-roles', 'Audit log → Roles &amp; permissions — role banana / permissions badalna.')}
${fig('a44-audit-role-entry', 'Role change entry ki detail — kaunsi permissions add/remove hui.')}
${bullets([
  'Audit log <b>append-only</b> hai — entries edit ya delete nahi hotin.',
  'Har mahine Users aur Roles categories review karein (chapter 10).',
])}
`,
};
