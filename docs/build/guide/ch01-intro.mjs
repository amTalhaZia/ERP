import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, table } from './lib.mjs';

export default {
  id: 'ch-intro',
  title: 'Introduction',
  subtitle: 'ERP kya hai, is system mein kya kya hai, aur yeh guide kaise use karein.',
  render: () => `
${h2('intro-what', '1.1 ERP kya hai?')}
<p class="lead"><b>ERP (Enterprise Resource Planning)</b> aik aisa software hai jis mein company ke saare departments — HR, payroll, accounts, sales, purchasing, stock, projects — aik hi jagah, aik hi database par kaam karte hain. Har department ka alag Excel sheet ya alag software rakhne ke bajaye, sab log aik hi system mein data enter karte hain, aur reports hamesha up-to-date rehti hain.</p>
<p>Misaal ke taur par: jab sales team aik <b>invoice</b> approve karti hai, to woh khud-ba-khud accounts ke <b>general ledger</b> mein post ho jati hai. Jab warehouse mein maal receive hota hai (goods receipt), to stock levels foran update ho jate hain. Payroll finalize hota hai to salary ka journal entry ban jata hai. Isi ko hum "one ledger of truth" kehte hain — aik hi sach, sab ke liye.</p>
${fig('owner-dashboard', 'Company Owner ka Dashboard — revenue, expenses, receivables, payables, employees aur pending approvals aik nazar mein.')}

${h2('intro-modules', '1.2 Modules ka overview')}
<p>Enterprise ERP mein yeh modules shamil hain. Har module sidebar (left side ka menu) mein apne section ke neeche nazar aata hai — lekin sirf tab jab aap ke role mein us ki permission ho.</p>
<div class="card-grid">
  <div class="card"><h4>Dashboard &amp; Approvals</h4><p>Executive KPIs, charts, aur aik inbox jahan har woh request aati hai jo aap ke faisle ka intezar kar rahi hai.</p></div>
  <div class="card"><h4>Company &amp; Branches</h4><p>Company profile, branding, branches, departments aur locations map.</p></div>
  <div class="card"><h4>Users &amp; Roles</h4><p>Users ko invite karna, roles assign karna, branch restriction, custom roles aur permissions.</p></div>
  <div class="card"><h4>HR</h4><p>Employees, org chart, attendance, leave, shifts &amp; holidays, performance reviews, HR reports.</p></div>
  <div class="card"><h4>Payroll</h4><p>Pay components, salary structures, tax tables, monthly payroll runs aur payslips.</p></div>
  <div class="card"><h4>Finance / Accounting</h4><p>Chart of accounts, double-entry journals, bank &amp; cash, tax rates, financial periods, financial statements.</p></div>
  <div class="card"><h4>Receivables &amp; Payables</h4><p>Customer invoices, receipts, supplier bills, payments, expense claims aur aging.</p></div>
  <div class="card"><h4>Sales &amp; CRM</h4><p>Customers, leads, pipeline board, quotations, sales orders, deliveries, sales reports.</p></div>
  <div class="card"><h4>Procurement</h4><p>Suppliers, purchase requisitions, purchase orders, goods receipts, procurement reports.</p></div>
  <div class="card"><h4>Inventory</h4><p>Products, stock levels, movements, transfers, adjustments, warehouses, valuation reports.</p></div>
  <div class="card"><h4>Projects &amp; Tasks</h4><p>Projects portfolio, Kanban board, milestones, members, time logging aur project expenses.</p></div>
  <div class="card"><h4>Documents, Reports, Audit</h4><p>Company files with versions/retention, reports &amp; exports (CSV, Excel, PDF), aur append-only audit log.</p></div>
</div>
<p>In ke ilawa <b>Platform</b> section bhi hai (${nav('Platform', 'Companies')}, ${ui('Platform users')}, ${ui('System')}) jo sirf platform owner/admin ko nazar aata hai — is se poori installation ki companies manage hoti hain.</p>

${h2('intro-multicompany', '1.3 Multi-company concept')}
<p>Yeh ERP <b>multi-company</b> hai: aik hi installation mein kai companies (tenants) chal sakti hain, aur har company ka data dusri se <b>bilkul alag (isolated)</b> rehta hai. Demo data mein do companies hain: <b>Northwind Trading (Demo)</b> (USD) aur <b>Indus Manufacturing (Demo)</b> (PKR).</p>
${bullets([
  `<b>Company</b> — sab se upar ka level. Har company ki apni currency, time zone, chart of accounts, roles aur users hote hain.`,
  `<b>Branch</b> — company ke andar offices/sites (jaise Head Office, Austin Office). Users ko specific branches tak mehdood kiya ja sakta hai.`,
  `<b>Department</b> — organisational units (Finance, HR, Sales…), jo HR, approvals aur reporting mein use hote hain.`,
  `<b>Membership</b> — aik user aik se zyada companies ka member ho sakta hai, har company mein alag role ke sath.`,
])}
<p>Topbar mein company ka naam (company switcher) aur ${ui('All branches')} (branch filter) nazar aata hai. Agar aap kai companies ke member hain to company switcher se dusri company select kar sakte hain; branch filter se dashboard aur lists sirf aik branch ka data dikhati hain.</p>
${note(`Platform staff (jaise ${c('platform@erp.test')}) companies <b>bana</b> aur <b>manage</b> kar sakte hain, lekin kisi company ka business data (employees, invoices waghera) tab tak <b>nahi dekh sakte</b> jab tak woh us company ke member na hon. Yeh jaan-boojh kar rakha gaya security design hai.`)}

${h2('intro-screen', '1.4 Screen ka layout')}
<p>Login ke baad har page ka layout aik jaisa hai:</p>
${table(['Hissa', 'Kya hai'], [
  ['<b>Sidebar</b> (left)', `Modules ka menu, sections mein: ${ui('Organisation')}, ${ui('People')}, ${ui('Finance')}, ${ui('Operations')}, ${ui('Insights')}. Neeche ${ui('Collapse')} button se sidebar chhota (sirf icons) ho jata hai.`],
  ['<b>Breadcrumbs</b>', `Topbar ke left mein — maslan ${c('Home / Accounting / Journal entries')} — taake pata rahe aap kahan hain.`],
  ['<b>Search…</b>', `Global search box — click karein to Search page khulta hai: employees, customers, suppliers, products, documents aur transactions.`],
  ['<b>Company switcher</b>', 'Current company ka naam; kai companies hon to yahan se switch karein.'],
  ['<b>Branch filter</b>', `${ui('All branches')} ya koi aik branch.`],
  ['<b>Bell icon</b>', 'Notifications — unread count ke sath.'],
  ['<b>Account menu</b>', `Aap ke initials (jaise ${c('OO')}). Is mein ${ui('My profile')}, ${ui('Security')}, ${ui('Notifications')}, ${ui('Appearance')}, theme (Light/Dark/System) aur sign out.`],
], { caption: 'Har page par nazar aane wale hisse' })}
${fig('owner-user-menu', 'Account menu — profile, security, notification preferences, appearance aur theme toggle.')}

${h2('intro-audience', '1.5 Yeh guide kis ke liye hai aur kaise parhein')}
${table(['Aap kaun hain', 'Kaun se chapters parhein'], [
  ['<b>IT person / jo system install karega</b>', 'Chapter 2 (Setup), 3 (Database), 4 (Pehla admin), 6 (Security), Appendix A (commands).'],
  ['<b>Business owner / Company administrator</b>', 'Chapter 4 (company aur owner), 5 (Users, roles &amp; permissions), 6 (Security), 7 (Daily use), 10 (FAQ).'],
  ['<b>Department staff</b> (HR, accounts, sales, store, purchase)', 'Chapter 7 mein apne module ka section, phir Chapter 10.'],
  ['<b>Aam employee</b>', 'Chapter 8 (Employee self-service) aur Chapter 9 (Mobile).'],
], { caption: 'Reading guide' })}
<p>Is guide mein in conventions ka khayal rakha gaya hai:</p>
${bullets([
  `Screen par jo label, button ya menu likha hai, woh <b>bilkul waisa hi English mein</b> highlight kiya gaya hai, jaise ${ui('New employee')} ya ${nav('HR', 'Employees')}.`,
  `Commands, file names aur settings ${c('is tarah')} code style mein hain.`,
  'Har kaam numbered steps mein likha hai — order mein follow karein.',
  'Screenshots demo company "Northwind Trading (Demo)" ke hain; aap ki company mein data alag hoga, lekin screens wohi hongi.',
])}
${tip('Rang-birangi boxes ka matlab: <b>Tip</b> = kaam aasaan karne ka mashwara; <b>Dhyan dein</b> = ghalti se bachne ki warning; <b>Security</b> = data aur access ki hifazat se mutaliq zaroori baat; <b>Note</b> = izafi maloomat.')}
${warn('Demo accounts aur demo companies (is guide mein screenshots wale) <b>sirf development/testing ke liye</b> hain. Production (asli business) server par demo data kabhi load na karein — Chapter 4 dekhein.')}
`,
};
