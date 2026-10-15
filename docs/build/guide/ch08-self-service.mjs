import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn } from './lib.mjs';

export default {
  id: 'ch-self-service',
  title: 'Employee self-service',
  subtitle: 'Aam employee kya dekhta hai aur apne kaam khud kaise karta hai.',
  render: () => `
${h2('ess-see', '8.1 Employee ko kya nazar aata hai')}
<p class="lead"><b>Employee</b> role mein sirf self-service permissions hain: apna leave, attendance, payslips, expense claims, tasks/time aur company documents dekhna. Is liye sidebar bohat chhota hota hai.</p>
${fig('employee-dashboard', 'Employee (employee@northwind.test) ka dashboard aur sidebar — sirf Dashboard, Company, HR, Payroll, Payables, Projects, Documents.')}
${bullets([
  `${nav('HR', 'Attendance')} aur ${nav('HR', 'Leave')} — apni attendance aur leave.`,
  `${nav('Payroll', 'My payslips')} — apni salary slips.`,
  `${nav('Payables', 'Expense claims')} — apne kharchon ke claims.`,
  `${ui('Projects')} — jin projects/tasks mein woh shamil hai, aur ${ui('My tasks')}.`,
  `${ui('Documents')} — company-wide documents (jaise policies).`,
])}
${note('Agar employee ko self-service screens par "Your account isn\'t linked to an employee record" nazar aaye, to HR ko kahein: <b>HR → Employees →</b> us ka record → <b>Edit → Linked user</b> mein us ka login select karein.')}

${h2('ess-attendance', '8.2 Attendance: Check in / Check out')}
${steps([
  `Subah kaam shuru karte waqt ${nav('HR', 'Attendance')} kholein.`,
  'Card par aaj ki tareekh, company ka time zone aur live ghari nazar aati hai; Status "Not checked in".',
  `${ui('Check in')} dabayein. Shift ke mutabiq late hone par late minutes khud calculate hote hain.`,
  `Kaam khatam karke ${ui('Check out')} dabayein — worked time record ho jata hai.`,
])}
${fig('employee-attendance', 'Self check-in card — tareekh, time zone, status, Check in / Check out.')}
${tip('Check-in/out bhool jayein to HR ko batayein — HR "Record attendance" se manual record daal sakta hai.')}

${h2('ess-leave', '8.3 Leave request karna')}
${steps([
  `${nav('HR', 'Leave')} kholein — ${ui('My leave')} tab mein "My balances" (har leave type ke din baqi) aur "My requests".`,
  `${ui('Request leave')} dabayein.`,
  'Leave type, start/end date aur reason likhein. Public holidays leave ke dinon ki ginti mein shamil nahi hotin.',
  `${ui('Submit request')} — request "Pending" ho jati hai aur approver ko notification jati hai.`,
  `Faisle tak pending request ${ui('Cancel')} ki ja sakti hai. Approve/reject hone par notification aati hai.`,
])}
${fig('employee-leave', 'My leave — balances (Annual 17 days left…), Request leave, aur requests ki list (Pending / Approved).')}

${h2('ess-payslips', '8.4 Payslips dekhna')}
<p>${nav('Payroll', 'My payslips')} — har mahine ka period, pay date, gross, deductions, net pay aur status. Payslip tab nazar aati hai jab company us mahine ka payroll <b>finalize</b> kar de.</p>
${fig('employee-payslips', 'My payslips — August aur September 2026, gross $3,890, net $3,376, Paid.')}

${h2('ess-expenses', '8.5 Expense claim')}
${steps([
  `${nav('Payables', 'Expense claims')} → ${ui('New claim')}.`,
  'Har kharche ki line: date, category, description, amount (aur tax).',
  'Save karein (draft), phir har line par receipt (PDF ya image, max 5 MB) attach karein.',
  `${ui('Submit')} — "The claim goes to an approver and can no longer be edited."`,
  'Reject ho to wajah nazar aati hai; claim edit karke dobara submit karein. Approve hone ke baad paisa payments ke zariye milta hai (status Paid).',
])}
${fig('employee-expenses', 'Employee ki apni expense claims — "Your out-of-pocket expense claims."')}

${h2('ess-tasks', '8.6 Tasks aur time')}
<p>${ui('Projects')} → ${ui('My tasks')} mein aap ko assign kiye gaye tasks hain. Task kholein, status badlein (maslan To do → In progress → In review), comment karein, aur ${ui('Log time')} se ghante darj karein.</p>
${fig('employee-projects-mytasks', 'Projects → My tasks — employee ke apne open tasks.')}

${h2('ess-limits', '8.7 Jo employee nahi kar sakta')}
<p>Employee doosron ka data (invoices, salaries, users…) nahi dekh sakta. Agar woh kisi aur page ka URL khud type kare, server inkaar karta hai:</p>
${fig('employee-forbidden-invoice-detail', 'Employee ne direct /finance/invoices/11 khola — "You don\'t have permission to perform this action."')}
${warn('Apna password kisi ke sath share na karein — har kaam aap ke naam se audit log mein likha jata hai.')}
`,
};
