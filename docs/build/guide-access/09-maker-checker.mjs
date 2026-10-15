import { h2, ui, nav, c, fig, bullets, table, note, tip } from '../guide/lib.mjs';

export default {
  id: 'acc-maker-checker',
  title: 'Maker–checker: banane wala approve nahi karta',
  subtitle: 'Approval separation ke qawaid aur system unhein kaise lagu karta hai.',
  render: () => `
${h2('mc-what', '9.1 Maker–checker kya hai?')}
<p class="lead">"Maker" woh hai jo koi request/document banata hai; "checker" woh jo usay approve karta hai. Dono alag log hon to ghalti ya dhoka pakarna aasaan hota hai. ERP yeh usool kai jagah khud lagu karta hai — chahe user ke paas approve ki permission ho.</p>
${table(['Kaam', 'Rule', 'System kya karta hai'], [
  ['Payroll run', 'Run banane wala usay approve nahi kar sakta', `Server: "You cannot approve a payroll run you created." (${c('payroll.self_approval')}); run page par note: "another user with the Run &amp; approve payroll permission must approve it."`],
  ['Purchase requisition', 'Requester apni requisition approve nahi kar sakta', 'Approvals inbox mein apni requisition nahi aati; approve sirf doosra approver'],
  ['Approvals inbox (sab types)', 'Apni requests kabhi nahi dikhtin', '"Requests waiting for your decision. Your own requests never appear here."'],
  ['Users', 'Koi apni membership edit/deactivate/remove nahi kar sakta', '"You cannot deactivate your own membership." / apna aakhri users-manage role nahi hata sakta'],
  ['Platform', 'Koi apni platform role nahi badal sakta', '"You cannot change your own platform role."'],
], { caption: 'System ke lagu kiye hue separation rules' })}

${h2('mc-example', '9.2 Misaal: manager ki apni requisition')}
<p>Demo mein <b>Manager User</b> ne requisition ${c('PR-2026-000005')} banayi hai. Us ke paas "Approve purchase requisitions" permission hai, phir bhi:</p>
${fig('a45-manager-approvals', 'manager@northwind.test ka Approvals inbox — "Your own requests never appear here." PR-2026-000005 is list mein nahi.')}
${fig('a46-manager-own-requisition', 'Manager apni hi requisition kholta hai — Approve / Reject buttons nahi hain.')}
${fig('a47-owner-can-approve-requisition', 'Wohi requisition Company Owner ke login se — Approve button maujood (doosra shakhs = checker).')}

${h2('mc-practice', '9.3 Roles bante waqt separation ka khayal')}
${bullets([
  'Jo invoices/bills <b>banata</b> hai (maslan Sales Manager, Procurement Officer) us ke paas <b>approve</b> na ho — default roles mein yahi hai: Sales Manager invoices bana sakta hai, approve Accountant/Owner karte hain; Procurement Officer PO bana sakta hai, approve Owner/Admin.',
  'Payroll ke liye kam az kam <b>do</b> log jin ke paas "Run &amp; approve payroll" ho — aik banaye, doosra approve kare. Warna run atak jayega.',
  'Custom role banate waqt aik hi role mein "create" aur "approve" dono dene se pehle sochein.',
  `Approvals ki history ${nav('Insights', 'Audit log')} mein milti hai (kis ne kab approve kiya).`,
])}
${note('Leave aur expense claims mein bhi approver alag hota hai: employee ki apni request us ke Approvals inbox mein nahi aati, manager/HR/accountant approve karta hai.')}
${tip(`Chhoti company mein jahan log kam hon, Owner aksar checker hota hai — lekin Owner bhi apna banaya hua payroll run approve nahi kar sakta, is liye payroll ${ui('New run')} HR banaye aur Owner approve kare.`)}
`,
};
