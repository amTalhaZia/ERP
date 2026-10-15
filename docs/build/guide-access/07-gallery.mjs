import { h2, h3, fig, table, note, c } from '../guide/lib.mjs';
import { roles, matrixTable, partialDetails } from '../guide/roles-data.mjs';

const G = [
  ['owner', 'Company Owner', 'Poora menu — Organisation, People, Finance, Operations, Insights (Audit log samet).'],
  ['admin', 'Company Administrator', 'Owner jaisa menu; payroll mein runs dekh/structures manage kar sakta hai, process nahi.'],
  ['hr', 'HR Manager', 'Company (view), HR, Payroll, Documents, Approvals, Reports.'],
  ['accountant', 'Accountant', 'Accounting, Receivables, Payables, financial reports; customers/suppliers sirf view.'],
  ['sales', 'Sales Manager', 'Sales &amp; CRM, invoices (bana sakta hai, approve nahi), products/stock view.'],
  ['inventory', 'Inventory Manager', 'Inventory poora, goods receipts, POs/sales orders view.'],
  ['procurement', 'Procurement Officer', 'Procurement (suppliers, requisitions, POs, goods receipts), bills banana.'],
  ['manager', 'Department Manager', 'Approvals (leave, requisitions, expenses), Projects, team HR view.'],
  ['employee', 'Employee', 'Sirf self-service: leave, attendance, payslips, expense claims, tasks, documents.'],
  ['auditor', 'Read-Only Auditor', 'Har module dekh sakta hai + Audit log; koi "New"/"Edit" button nahi.'],
];

export default {
  id: 'acc-gallery',
  title: 'Kis role ko kya dikhta hai',
  subtitle: 'Das default roles ke dashboards/sidebars aur poora permission matrix.',
  render: () => `
${h2('gal-intro', '7.1 Sidebar hi pehli nishani hai')}
<p class="lead">Har user ko sirf woh menus nazar aate hain jin ki us ke role mein permission ho. Neeche Northwind demo company ke 10 demo logins (${c('<role>@northwind.test')}) ke screenshots hain — login ke foran baad ka dashboard aur poora sidebar.</p>
${note('Kuch roles (jaise Employee) ke dashboard par finance widgets "No data for this period" dikhate hain — un ke paas woh data dekhne ki permission nahi; yeh khaali hona normal hai.')}

${h2('gal-shots', '7.2 Gallery')}
<div class="gallery">${G.map(([u, name, cap]) => fig(`g-${u}`, `<b>${name}</b> — ${cap}`)).join('')}</div>

${h2('gal-matrix', '7.3 Permission matrix (module × role)')}
<p>Yeh table database mein assign ki gayi asli permissions se banayi gayi hai (source: ${c('backend/config/erp.php')} role_templates).</p>
<p><span class="m-full">✓</span> = poora access &nbsp; <span class="m-view">👁</span> = sirf dekhna &nbsp; <span class="m-part">◐</span> = kuch actions &nbsp; <span class="m-none">—</span> = koi access nahi</p>
${matrixTable()}
${h3('◐ ki tafseel')}
${table(['Area', 'Role', 'Yeh actions milte hain'], partialDetails(), { compact: true })}
${h3('Roles ka khulasa')}
${table(['Role', 'Description (system)', 'Permissions'], roles.map((r) => [`<b>${r.name}</b>`, r.description, String(r.perms.size)]), { compact: true })}
`,
};
