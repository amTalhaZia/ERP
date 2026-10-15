import { h2, ui, nav, c, fig, steps, bullets, tip, note, warn } from '../guide/lib.mjs';

export default {
  id: 'acc-custom-role',
  title: 'Custom role banana aur assign karna',
  subtitle: '"Branch Sales Supervisor (Demo)" — sirf zaroori permissions ke sath.',
  render: () => `
${h2('cr-why', '6.1 Custom role kab?')}
<p class="lead">Jab 10 default roles mein se koi bhi bilkul fit na ho — maslan aap chahte hain ke branch ka sales supervisor customers dekhe, quotations banaye, sales orders dekhe aur reports dekhe, lekin invoices, leads ya stock nahi. Is misaal mein role ki permissions:</p>
${bullets([
  `${c('dashboard.view')} — View executive dashboard`,
  `${c('customers.view')} — View customers`,
  `${c('quotations.view')} + ${c('quotations.manage')} — View / Manage quotations`,
  `${c('sales_orders.view')} — View sales orders`,
  `${c('reports.view')} — View reports`,
])}

${h2('cr-create', '6.2 Role banana — step by step')}
${steps([
  `${nav('Users & roles', 'Roles & permissions')} kholein, upar right ${ui('New role')} <span class="badge-n">1</span>.`,
])}
${fig('a20-roles-new-role-button', 'Roles &amp; permissions — (1) New role.')}
${steps([
  `${ui('Name')} <span class="badge-n">1</span> (yahan "Branch Sales Supervisor (Demo)") aur ${ui('Description')} likhein.`,
])}
${fig('a21-new-role-name', 'New role drawer — (1) role ka naam aur description.')}
${steps([
  `${ui('Filter permissions')} <span class="badge-n">2</span> box mein likh kar permission dhoondein — maslan "quotation".`,
  `Matching checkboxes tick karein <span class="badge-n">3</span>: ${ui('View quotations')}, ${ui('Manage quotations')}. Har checkbox ke neeche technical code (${c('quotations.view')}) bhi likha hai.`,
  'Filter saaf karke baqi permissions bhi isi tarah tick karein. Upar "6 permissions selected" ginti nazar aati hai.',
])}
${fig('a22-new-role-filter-quotations', 'Filter permissions — (2) "quotation" search, (3) dono quotation permissions tick. Upar: "6 permissions selected".')}
${steps([
  `Poori list dobara dekh lein, phir ${ui('Create role')} <span class="badge-n">4</span> dabayein.`,
])}
${fig('a23-new-role-sales-section', 'Saari selected permissions — (4) Create role.')}
${fig('a24-custom-role-card', 'Naya role card — "Custom" badge, 0 users, 6 permissions; custom role ka Edit aur Delete dono.')}

${h2('cr-assign', '6.3 Custom role assign karna')}
${steps([
  `${nav('Users & roles', 'Users')} → ${ui('Invite user')} (naya shakhs) — ya maujooda user ke ⋯ → ${ui('Edit access')}.`,
  `${ui('Roles')} list mein custom role bhi nazar aata hai — ${ui('Branch Sales Supervisor (Demo)')} <span class="badge-n">1</span> tick karein.`,
  `Branch restriction: ${ui('Access to all branches')} off, ${ui('Boston Office (BOS)')} tick.`,
  `${ui('Send invitation')} / ${ui('Save access')}.`,
])}
${fig('a27-assign-custom-role', 'Invite drawer mein custom role (1) — sirf Boston Office ke liye.')}
<p>Supervisor login karta hai to us ka sidebar bohat mukhtasar hota hai — sirf dashboard, Sales &amp; CRM (customers, quotations, sales orders, sales reports) aur Reports:</p>
${fig('g-custom-sales-supervisor', 'branch.sales@northwind.test (custom role) ka dashboard aur sidebar.')}
${fig('g-custom-sales-supervisor-quotations', 'Supervisor Quotations page par — "Manage quotations" ki wajah se naya quotation bana sakta hai.')}

${h2('cr-system', '6.4 System roles edit karna aur Owner role lock')}
<p>System roles (jaise Accountant) ka <b>naam aur description fixed</b> hain, lekin permissions badli ja sakti hain: "System role: the name is fixed, but you can adjust its permissions." Tabdeeli us role ke <b>tamam</b> users par foran lagu hoti hai.</p>
${fig('a25-system-role-accountant', 'Accountant (system role) editor — naam/description disabled, permissions checkboxes editable.')}
${fig('a26-owner-role-locked', 'Company Owner role — "The Company Owner role always has every permission and cannot be edited." Sirf View.')}
${warn('System role ki permission badalne se pehle sochein: maslan Accountant role se kuch hataya to company ke <b>har</b> accountant par asar hoga. Aksar behtar hai ke system role ko chhor kar naya custom role banayein.')}
${note('Custom role tabhi delete hota hai jab us ke paas koi user na ho — pehle users se role hatayein. System roles delete nahi hote.')}
${tip(`Har role tabdeeli audit log mein "Roles &amp; permissions" category mein record hoti hai (chapter 8.5).`)}
`,
};
