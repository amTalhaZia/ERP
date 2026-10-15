import { h2, ui, nav, c, fig, steps, bullets, tip, note, warn, table } from '../guide/lib.mjs';

export default {
  id: 'acc-level3',
  title: 'Admin → department heads (branch restriction ke sath)',
  subtitle: 'HR Manager, Accountant aur Department Manager — har aik sirf apni branch tak.',
  render: () => `
${h2('l3-plan', '5.1 Misaal ka plan')}
<p class="lead">Ab Company Administrator (${c('sub.admin@northwind.test')}) teen department heads banata hai. Har aik ko sirf <b>aik branch</b> ka access milega:</p>
${table(['Shakhs (fictional)', 'Login', 'Role', 'Branch'], [
  ['Branch HR (Guide Demo)', c('branch.hr@northwind.test'), 'HR Manager', 'Boston Office (BOS)'],
  ['Branch Accountant (Guide Demo)', c('branch.accountant@northwind.test'), 'Accountant', 'Chicago Office (CHI)'],
  ['Branch Manager (Guide Demo)', c('branch.manager@northwind.test'), 'Department Manager', 'Austin Office (AUS)'],
], { caption: 'Is chapter mein banaye gaye users' })}

${h2('l3-steps', '5.2 Branch-restricted user invite karna')}
${steps([
  `Admin ${nav('Users & roles', 'Users')} → ${ui('Invite user')} dabata hai.`,
  `${ui('Full name')} aur ${ui('Work email')} bharta hai.`,
  `${ui('Roles')} mein munasib role tick karta hai — yahan ${ui('HR Manager')}.`,
  `${ui('Access to all branches')} <span class="badge-n">2</span> ko <b>off</b> karta hai — neeche ${ui('Allowed branches')} list khul jati hai.`,
  `Sirf us branch ko tick karta hai — yahan ${ui('Boston Office (BOS)')} <span class="badge-n">3</span>.`,
  `${ui('Send invitation')} <span class="badge-n">4</span>.`,
  'Yahi amal Accountant (Chicago) aur Department Manager (Austin) ke liye dohrayein.',
])}
${fig('a18-invite-branch-hr-restricted', 'HR Manager role tick; (2) Access to all branches OFF, (3) sirf Boston Office, (4) Send invitation.')}
${fig('a19-users-with-branches', 'Teen naye users — Branches column mein "All" ke bajaye "1 selected" (branch-restricted); Last sign-in "Never".')}
${tip(`Kis branch ka access hai, yeh dekhne ke liye row ke ⋯ menu → ${ui('Edit access')} kholein — wahan ticked branches nazar aati hain.`)}

${h2('l3-effect', '5.3 Branch restriction ka asar')}
<p>Branch-restricted user ke topbar se ${ui('All branches')} filter ghaib ho jata hai, aur is version mein yeh modules us ki allowed branches tak mehdood hain: <b>customer invoices, supplier bills, payments/receipts, journal entries, payroll runs aur payslips, warehouses, projects, dashboard aur reports/exports</b>.</p>
${warn(`<b>Is version ki hadd (known limitation):</b> kuch lists abhi branch restriction lagu <b>nahi</b> kartin — maslan ${nav('HR', 'Employees')}. Neeche screenshot mein Boston tak mehdood Branch HR ko Head Office aur Chicago ke employees bhi nazar aa rahe hain. Isi tarah sales orders, purchase orders, attendance, leave aur stock lists bhi abhi branch se filter nahi hotin. Jab tak yeh theek na ho, branch restriction ko in modules ke liye hifazati deewar na samjhein — role ki permissions hi asal hadd hain.`, 'Dhyan dein — known limitation')}
${fig('a28-branch-hr-employees', 'branch.hr@northwind.test (sirf Boston) ki Employees list — abhi doosri branches (Head Office, Chicago) ke employees bhi dikh rahe hain; yeh known limitation hai.')}
${bullets([
  'Restriction role ki permissions ke <b>upar</b> lagti hai: pehle role decide karta hai kaunsa module, phir branch decide karti hai kaunse records.',
  'Jin records par branch set nahi (company-wide), woh role ke mutabiq nazar aate hain.',
  'Aik user ko kai branches di ja sakti hain — maslan regional manager ko do offices.',
])}
${note('HR Manager role mein "Run &amp; approve payroll" bhi hai. Branch HR ko sirf apni branch ka payroll chalana ho to payroll run banate waqt scope mein branch chunein; agar payroll bilkul nahi dena to custom role banayein (chapter 6).')}
`,
};
