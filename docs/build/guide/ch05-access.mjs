import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, security, table } from './lib.mjs';
import { roles, matrixTable, partialDetails } from './roles-data.mjs';

export default {
  id: 'ch-access',
  title: 'Users, Roles & Permissions',
  subtitle: 'Kis ko kya access dena hai — invite, roles, branch restriction, custom roles aur best practices.',
  render: () => `
${h2('acc-concepts', '5.1 Bunyadi concepts')}
${bullets([
  `<b>Permission</b> — aik chhota sa haq, jaise "Approve invoices" (${c('invoices.approve')}) ya "View employees" (${c('employees.view')}). System mein ~90 permissions hain (Appendix A.3).`,
  '<b>Role</b> — permissions ka aik bundle, jaise "Accountant" ya "HR Manager". User ko permissions direct nahi, roles ke zariye milti hain.',
  `<b>User ki access = us ke tamam roles ki permissions ka majmooa (union)</b>. Aik user ke do roles hon to dono ki permissions milti hain.`,
  '<b>Company-wise</b> — roles har company ke andar alag hote hain. Aik shakhs Company A mein Accountant aur Company B mein Employee ho sakta hai.',
  '<b>Branch restriction</b> — user ko sirf kuch branches ke data tak mehdood kiya ja sakta hai.',
])}
${tip('Sidebar mein sirf wohi menus nazar aate hain jin ki permission ho. Lekin asal hifazat server par hoti hai — Chapter 6 dekhein.')}

${h2('acc-roles', '5.2 Das (10) default roles')}
<p>Har nayi company ko yeh 10 system roles khud-ba-khud milte hain:</p>
${table(['Role', 'Kis ke liye', 'Permissions'], roles.map((r) => [`<b>${r.name}</b>`, r.description, String(r.perms.size)]), { caption: 'Default roles (Northwind demo company se ginti)' })}
${fig('owner-roles', 'Users &amp; roles → Roles &amp; permissions: har role card par users aur permissions ki tadaad. "Company Owner" par Locked badge, baqi par System badge.')}

${h2('acc-matrix', '5.3 Permission matrix (module × role)')}
<p>Neeche wali table batati hai ke kaunsa role kis module mein kya kar sakta hai. Yeh database mein assign ki gayi asli permissions se banayi gayi hai.</p>
<p><span class="m-full">✓</span> = poora access (dekhna + banana/badalna/approve) &nbsp; <span class="m-view">👁</span> = sirf dekhna (view) &nbsp; <span class="m-part">◐</span> = kuch actions (neeche detail) &nbsp; <span class="m-none">—</span> = koi access nahi</p>
${matrixTable()}
${h3('◐ (partial) ka matlab — tafseel')}
${table(['Area', 'Role', 'Yeh actions milte hain'], partialDetails(), { compact: true, caption: 'Partial access ki detail' })}
${note(`Ahem farq: <b>Company Administrator</b> ke paas sab kuch hai <b>siwaye</b> "Run &amp; approve payroll" (${c('payroll.process')}) ke — payroll chalana HR Manager/Owner ka kaam hai. <b>Procurement Officer</b> purchase orders bana/manage kar sakta hai lekin approve nahi — PO approval Owner/Admin karte hain. <b>Read-Only Auditor</b> har module dekh sakta hai, audit log bhi, aur reports export kar sakta hai, lekin kuch badal nahi sakta.`)}

${h2('acc-invite', '5.4 Naya user invite karna')}
${fig('owner-users', 'Users list — har user ke roles, branches, last sign-in aur status. Role aur status se filter bhi kar sakte hain.')}
${steps([
  `Sidebar mein ${nav('Users & roles', 'Users')} kholein.`,
  `Upar right ${ui('Invite user')} button dabayein — right side se aik drawer khulega.`,
  `${ui('Full name')} aur ${ui('Work email')} bharein (dono lazmi).`,
  `${ui('Roles')} mein aik ya zyada roles tick karein. Hint: "Permissions are the union of all assigned roles."`,
  `${ui('Access to all branches')} — default on. Agar user ko sirf kuch branches tak rakhna hai to off karein aur ${ui('Allowed branches')} mein branches chunein (5.5).`,
  'Invite/save karein. Naye shakhs ko email milti hai jis ke link se woh apna password khud set karta hai. Agar email pehle se kisi account ki hai, to usi account ko company mein add kar diya jata hai.',
])}
${fig('owner-invite-user-drawer', 'Invite user drawer — naam, work email, roles, aur "Access to all branches" switch.')}
${tip(`User ne abhi tak login nahi kiya to row ke actions menu mein ${ui('Resend invitation')} option hota hai.`)}

${h2('acc-branch', '5.5 Branch restriction')}
<p>Bari companies mein aksar aik branch ka manager doosri branch ka data na dekhe. Iske liye:</p>
${steps([
  `User ko invite karte waqt (ya baad mein ${ui('Edit access')} se) ${ui('Access to all branches')} switch <b>off</b> karein — "Turn off to restrict this user to specific branches."`,
  `${ui('Allowed branches')} mein sirf woh branches select karein jahan ka data user dekh sake.`,
  `Save karein. Users list ke ${ui('BRANCHES')} column mein ab "All" ke bajaye branch names nazar aayenge.`,
])}
${note('Is version mein branch restriction yeh records mehdood karti hai: customer invoices, supplier bills, payments/receipts, journal entries, payroll runs/payslips, warehouses, projects, dashboard aur reports/exports. Company-level settings aur jin records par branch nahi, woh role ki permission ke mutabiq dikhte hain.')}
${warn('<b>Known limitation:</b> kuch lists abhi branch restriction lagu nahi kartin — maslan HR → Employees, sales orders, purchase orders, attendance, leave aur stock. Branch-restricted user in modules mein doosri branches ka data bhi dekh sakta hai (agar role mein us module ki permission ho). In modules ke liye asal hadd role ki permissions hain.')}

${h2('acc-edit-role', '5.6 Role ki permissions badalna aur custom role banana')}
${h3('System role edit karna')}
${steps([
  `${nav('Users & roles', 'Roles & permissions')} kholein.`,
  `Jis role ko badalna hai (maslan <b>Accountant</b>) us ke card par ${ui('Edit')} dabayein.`,
  `Drawer mein note hoga: "System role: the name is fixed, but you can adjust its permissions." — naam/description nahi badlenge, permissions badal sakte hain.`,
  `${ui('Filter permissions')} box mein likh kar permission dhoondein, ya module ke ${ui('All')} checkbox se poora module on/off karein.`,
  `Har permission ke neeche us ka technical code (jaise ${c('company.view')}) bhi likha hai.`,
  `${ui('Save role')} dabayein. Is role wale tamam users par tabdeeli foran lagu ho jati hai.`,
])}
${fig('owner-role-editor-accountant', 'Accountant role editor — module-wise permission checkboxes, "31 permissions selected", Filter permissions aur Save role.')}
${h3('Custom role banana')}
${steps([
  `Roles page par upar right ${ui('New role')} dabayein.`,
  `${ui('Name')} (maslan "Branch Cashier") aur ${ui('Description')} likhein.`,
  'Sirf zaroori permissions tick karein (least privilege — 5.8).',
  `${ui('Create role')} dabayein, phir user ko ${ui('Edit access')} se yeh role assign karein.`,
])}
${note(`<b>Company Owner role locked hai:</b> "The Company Owner role always has every permission and cannot be edited." Is mein hamesha saari permissions rehti hain. Custom role delete karne ke liye pehle us ke tamam users se role hatayein; system roles delete nahi hote.`)}

${h2('acc-remove', '5.7 User ko edit, deactivate ya remove karna')}
<p>Users list mein har row ke aakhir mein actions menu (⋯) hai:</p>
${table(['Action', 'Kya hota hai'], [
  [ui('Edit access'), `Roles, branches aur ${ui('Active in this company')} switch badlein.`],
  [ui('Active in this company') + ' = off', '"Inactive members cannot sign in to this company." User ka record aur history baqi rehti hai; agle request par woh is company se bahar ho jata hai. Wapas on karke access bahal kar sakte hain.'],
  [ui('Resend invitation'), 'Sirf un users ke liye jinhon ne kabhi login nahi kiya.'],
  [ui('Remove from company'), '"They lose access to this company immediately. Their account and history in other companies are not affected."'],
], { caption: 'User actions' })}
${warn('Aap apne aap ko remove ya deactivate nahi kar sakte, aur apna woh aakhri role nahi hata sakte jis se users manage hote hain — taake company baghair admin ke na reh jaye.')}
${tip('Koi employee company chhore to <b>usi din</b> us ki membership inactive ya remove karein. Delete karne ke bajaye inactive karna behtar hai agar audit/history ke liye record chahiye.')}

${h2('acc-best', '5.8 Best practices')}
${bullets([
  '<b>Least privilege</b> — har shakhs ko sirf utni access dein jitni us ke kaam ke liye zaroori hai. "Sab ko Admin bana do" kabhi nahi.',
  '<b>Maker–checker (separation of duties)</b> — jo record banaye, wohi approve na kare. System khud bhi kuch jagah yeh rok lagata hai: jis user ne <b>payroll run banaya</b> woh usay approve nahi kar sakta ("You cannot approve a payroll run you created."), aur requisition banane wala apni requisition approve nahi kar sakta. Approvals inbox mein "Your own requests never appear here."',
  '<b>Owner accounts kam rakhein</b> — Company Owner role 1–2 logon tak; roz ka admin kaam Company Administrator se.',
  '<b>Shared accounts nahi</b> — har shakhs ka apna login, taake audit log mein sahi naam aaye.',
  '<b>2FA</b> — owners, admins, accountants aur HR ke liye lazmi.',
  '<b>Har mahine review</b> — Users list ko "Last sign-in" se dekhein; purane/ghair-zaroori accounts inactive karein.',
  '<b>Bahar ke auditor</b> ko <i>Read-Only Auditor</i> role dein, aur audit khatam hote hi remove karein.',
])}
`,
};
