import { h2, h3, ui, nav, c, fig, steps, bullets, tip, note, warn, security, table } from './lib.mjs';

const flow = (...s) => `<div class="flow">${s.map((x, i) => `${i ? '<span class="ar">→</span>' : ''}<span class="st">${x}</span>`).join('')}</div>`;

export default {
  id: 'ch-daily',
  title: 'Daily use — module by module',
  subtitle: 'Har module ka maqsad, aam kaam step-by-step, aur woh qawaid jo yaad rakhne zaroori hain.',
  render: () => `
<p class="lead">Is chapter ke screenshots <b>Company Owner</b> (${c('owner@northwind.test')}) ke hain jis ke paas har permission hai. Aap ke role ke mutabiq kuch menus ya buttons nazar nahi aayenge — yeh normal hai (Chapter 5).</p>
${h3('Har list page par aik jaisi cheezein')}
${bullets([
  `Upar right <b>primary button</b> (jaise ${ui('New employee')}, ${ui('New invoice')}) naya record banata hai.`,
  `Filters (status, date, branch…) aur search box list ko chhota karte hain; ${ui('Columns')} se columns chhupa/dikha sakte hain.`,
  `Column heading par click se sorting; neeche ${ui('Rows per page')} (10/25/50/100) aur page numbers.`,
  'Row par click karne se detail page ya drawer khulta hai; row ke aakhir mein ⋯ menu mein Edit/Delete jaise actions.',
  'Status rang-daar badges mein dikhta hai (Draft, Pending approval, Approved, Paid…).',
])}

${h2('daily-dashboard', '7.1 Dashboard')}
<p>Dashboard business ki sehat aik nazar mein dikhata hai. Upar right ${ui('MTD')} / ${ui('30D')} / ${ui('90D')} / ${ui('YTD')} / ${ui('12M')} se period badlein; topbar ke branch filter se aik branch ka data dekhein.</p>
${table(['Card / widget', 'Kya batata hai'], [
  ['Revenue, Expenses, Net profit', 'General ledger mein posted amounts, pichle barabar period se muqabla (% change).'],
  ['Outstanding receivables / payables', 'Customers se lena aur suppliers ko dena baqi.'],
  ['Employees, Active products, Pending approvals', 'Headcount, active products, aur aap ke faisle ke muntazir items.'],
  ['Revenue vs expenses chart, By branch', 'Waqt aur branch ke hisaab se trend.'],
  ['Recent invoices, Pending approvals, Low stock', 'Shortcut lists — "View all" / "Inbox" / "View" se poori list.'],
  ['Headcount by department, Recent activity', 'HR distribution aur haaliya audit activity.'],
], { caption: 'Dashboard widgets' })}

${h2('daily-approvals', '7.2 Approvals inbox')}
<p>${ui('Approvals')} har us cheez ki aik jagah list hai jo <b>aap ke faisle</b> ka intezar kar rahi hai: leave requests, purchase requisitions, purchase orders, sales orders, invoices, bills aur expense claims. Page par likha hai: "Your own requests never appear here." — apni banayi hui cheez aap ko approve ke liye nahi dikhti (maker–checker).</p>
${steps([
  `Sidebar mein ${ui('Approvals')} kholein. Upar tabs mein har type ki ginti hoti hai, jaise ${ui('All (9)')}, ${ui('Leave request (3)')}.`,
  'Kisi item par click karein — us ka detail page khulta hai.',
  `Detail parh kar ${ui('Approve')} ya ${ui('Reject')} karein (reject par wajah likhein).`,
])}
${fig('owner-approvals', 'Approvals inbox — type-wise tabs, har request ka khulasa aur kis ne kab bheja.')}

${h2('daily-notifications', '7.3 Notifications')}
<p>Bell icon par unread ginti nazar aati hai. ${ui('Notifications')} page par ${ui('All')} / ${ui('Unread')} filter aur ${ui('Mark all read')} button hai. Notifications approvals, assignments, low-stock alerts, export tayyar hone waghera par aati hain. Kis event ki notification app mein aur kis ki email par aaye, yeh ${nav('Account menu', 'Notifications')} (${c('/settings/notifications')}) mein set karein; security emails hamesha aati hain.</p>
${fig('owner-notifications', 'Notifications — leave approvals ki requests aur doosre alerts.')}

${h2('daily-company', '7.4 Company, Branches, Departments & Map')}
${h3('Company profile')}
<p>${nav('Company', 'Profile & settings')} mein logo (${ui('Upload logo')}, PNG/JPG/WebP/SVG, max 2 MB), legal name, tax/registration numbers, registered address aur localisation &amp; finance settings hain. Badal kar ${ui('Save changes')} dabayein.</p>
${fig('owner-company-profile', 'Company profile — Branding, Legal &amp; contact, Registered address, Localisation &amp; finance.')}
${h3('Branch banana')}
${steps([
  `${nav('Company', 'Branches')} kholein → ${ui('New branch')}.`,
  'Naam, code, address aur (map ke liye) location bharein; head office ho to mark karein.',
  'Save karein. Branch ab users ki branch restriction, employees, warehouses aur reports mein available hai.',
])}
${fig('owner-branch-drawer', 'New branch drawer — branch ki details. Save se pehle drawer band karne par kuch save nahi hota.')}
${h3('Departments aur map')}
<p>${nav('Company', 'Departments')} mein ${ui('New department')} se department banayein; parent department chun kar hierarchy (jaise Finance → Accounts Payable) bana sakte hain. ${nav('Company', 'Locations map')} par branches, warehouses, customers aur suppliers jin ke coordinates saved hain, map par nazar aate hain (${ui('Map')} / ${ui('List')}).</p>
${fig('owner-departments', 'Departments — code, parent, branch, sub-departments ki tadaad.')}
${fig('owner-locations-map', 'Locations map — OpenStreetMap par branches, warehouses, customers aur suppliers ke filters.')}

${h2('daily-hr', '7.5 HR')}
${h3('Employees')}
${fig('owner-hr-employees', 'HR → Employees — department, branch, designation, status aur type ke filters.')}
${steps([
  `${nav('HR', 'Employees')} kholein → ${ui('New employee')}.`,
  'Sections bharein: <b>Personal</b> (first/last name), <b>Contact &amp; address</b> (work email lazmi), <b>Emergency contact</b>, <b>Employment</b> (branch, department, designation, manager, type, hire date), <b>Payroll basics</b> (base salary), <b>Identity, bank &amp; tax</b>, <b>Linked user</b>, <b>Notes</b>. Employee code khud ban jata hai.',
  `${ui('Create employee')} dabayein.`,
  `<b>Linked user</b> mein us shakhs ka login select karna zaroori hai — warna woh self-service (check-in, leave, payslips) use nahi kar sakega.`,
])}
${fig('owner-hr-employee-new', 'New employee form — "The employee code is assigned automatically."')}
${fig('owner-hr-employee-profile', 'Employee profile — tabs: Overview, Contracts, Documents, Attendance, Leave, Shifts, Performance. Linked login yahan nazar aata hai.')}
${tip(`Kisi ka manager (Reports to) set karein to woh ${nav('HR', 'Org chart')} mein sahi jagah aata hai. Employee chhore to ${ui('Terminate')} use karein — record delete na karein.`)}
${fig('owner-hr-org-chart', 'Org chart — reporting lines; Expand all / Collapse all.')}
${h3('Attendance')}
<p>${nav('HR', 'Attendance')} mein teen tabs hain: ${ui('Records')} (rozana check-ins aur manual records), ${ui('Bulk entry')} (kai employees ki attendance aik sath) aur ${ui('Summary')} (period ka present/late/absent khulasa). ${ui('Record attendance')} se HR manual record daal sakta hai. Employees khud ${ui('Check in')} / ${ui('Check out')} karte hain (Chapter 8).</p>
${fig('owner-hr-attendance', 'Attendance records — status, worked time, late minutes. Owner ka login employee record se linked nahi, is liye upar self-service ka note aa raha hai.')}
${h3('Leave')}
<p>${nav('HR', 'Leave')} tabs: ${ui('My leave')}, ${ui('Approvals')} (ginti ke sath), ${ui('All requests')}, ${ui('Balances')}, ${ui('Leave types')}.</p>
${steps([
  `${ui('Approvals')} tab kholein — pending requests ki list aati hai.`,
  'Employee, type, dates, days aur reason dekhein.',
  `${ui('Approve')} ya ${ui('Reject')} dabayein. Employee ko notification chali jati hai aur balance update hota hai.`,
])}
${fig('owner-hr-leave-approvals', 'Leave → Approvals — har request par Approve / Reject.')}
${fig('owner-hr-leave-balances', 'Leave → Balances — har employee ka leave type-wise balance.')}
${h3('Shifts, Performance, HR reports')}
${bullets([
  `${nav('HR', 'Shifts & holidays')}: working hours/days (shifts) late aur worked-time ka hisaab chalate hain; public holidays leave ke dinon mein shumar nahi hotin. Shift employee profile se assign hoti hai.`,
  `${nav('HR', 'Performance')}: ${ui('New review')} se review cycle; status Draft → Submitted → Acknowledged.`,
  `${nav('HR', 'HR reports')}: ${ui('Headcount')}, ${ui('Attendance')}, ${ui('Leave')} — joiners, leavers, department/branch-wise charts.`,
])}
${fig('owner-hr-shifts', 'Shifts &amp; holidays — Standard Day shift (09:00–18:00, 1h break) aur public holidays.')}
${fig('owner-hr-reports', 'HR reports — headcount, joiners/leavers aur department/branch charts.')}

${h2('daily-payroll', '7.6 Payroll')}
<p>Payroll ka har mahine ka run is order mein chalta hai — har qadam ka record aur waqt run page par stepper mein nazar aata hai:</p>
${flow('Draft', 'Calculated', 'Approved', 'Finalized', 'Paid')}
${table(['Qadam', 'Kaun / kya hota hai'], [
  [ui('New run'), 'Period, pay date aur scope (company-wide / branch / department) chun kar draft run banta hai.'],
  [ui('Calculate'), 'Payslips current salaries, attendance/unpaid leave, pay components aur active tax table se bante hain. Status Calculated. Zaroorat ho to ' + ui('Recalculate') + '.'],
  [ui('Approve'), '<b>Kisi doosre</b> shakhs ka kaam jis ke paas "Run &amp; approve payroll" ho. Run banane wala approve nahi kar sakta.'],
  [ui('Finalize'), 'Ek payroll journal entry general ledger mein post hoti hai; run number type karke confirm karna parta hai. "This cannot be undone." Payslips employees ko dikhne lagti hain.'],
  [ui('Mark paid'), 'Salary ki adaigi record hoti hai (payment journal).'],
  [ui('Cancel run'), 'Draft/Calculated/Approved run cancel ho sakta hai; record audit ke liye rehta hai, kuch post nahi hota.'],
], { caption: 'Payroll run ke actions' })}
${fig('owner-payroll-runs', 'Payroll runs — har run ka period, scope, pay date, employees, gross, net aur status.')}
${fig('owner-payroll-run-detail', 'Approved run (October 2026) — stepper, gross/deductions/tax/net cards; agla qadam Finalize.')}
${fig('owner-payroll-run-paid', 'Paid run — "This run is finalized and posted to the general ledger; it can no longer be changed."')}
${warn('Finalize se pehle payslips achhi tarah check karein — finalize ke baad run badla nahi ja sakta. Ghalti ho to accounts team journal ke zariye correction karegi.')}
${h3('Payroll setup')}
<p>${nav('Payroll', 'Salary structures')} (page ka naam "Payroll setup") mein chaar tabs: ${ui('Pay components')} (allowances, deductions, employer contributions — fixed ya % of base), ${ui('Salary structures')}, ${ui('Tax tables')} aur ${ui('Employee salaries')}.</p>
${fig('owner-payroll-components', 'Pay components — kind, calculation, default, tax treatment aur GL account.')}
${fig('owner-payroll-tax-tables', 'Tax tables — "Tax rules are configurable — compliance is your responsibility." Active tax table na ho to tax zero aur payslip par "No tax table" flag.')}
${warn('System mein kisi mulk ka tax law built-in <b>nahi</b>. Tax brackets aur contributions khud enter karein aur kisi qualified tax adviser se review karwayein.')}

${h2('daily-finance', '7.7 Finance / Accounting')}
${h3('Chart of accounts')}
<p>${nav('Accounting', 'Chart of accounts')} — har ledger account (code, naam, type: Asset/Liability/Equity/Revenue/Expense). ${ui('New account')} se naya account. Nayi company ko default chart khud milta hai.</p>
${fig('owner-finance-accounts', 'Chart of accounts — code, type, subtype, flags (Cash, Bank, Receivable…).')}
${h3('Journal entry banana (double-entry)')}
${steps([
  `${nav('Accounting', 'Journal entries')} → ${ui('New entry')}.`,
  `${ui('Date')}, ${ui('Reference')} aur ${ui('Memo')} bharein.`,
  'Har line mein account chunein, description likhein, aur <b>ya</b> Debit <b>ya</b> Credit amount (aik line mein dono nahi).',
  `Zaroorat ho to ${ui('Add line')}. Neeche totals barabar hon to ${ui('Balanced')} badge aata hai — warna save button band rehta hai.`,
  `${ui('Save draft')} — entry draft ban jati hai.`,
  `Draft khol kar ${ui('Post')} → "Posting writes to the general ledger. Posted entries cannot be edited — only reversed."`,
])}
${fig('owner-finance-journal-new', 'New journal entry — Rent (6100) debit 2,500 aur Bank (1010) credit 2,500; "Balanced" badge. (Example — save nahi kiya gaya.)')}
${fig('owner-finance-journal-detail', 'Posted journal entry — lines, totals, period, source, kis ne banaya aur post kiya; Reverse button.')}
${warn(`<b>Posted entry edit nahi hoti.</b> Ghalti ho to entry khol kar ${ui('Reverse')} karein — system ulti (reversing) entry post karta hai, phir sahi entry dobara banayein. Invoices, bills, payments, payroll aur stock adjustments apni journal entries khud banate hain (Source column).`)}
${h3('Financial periods, bank, tax')}
${bullets([
  `${nav('Accounting', 'Financial periods')}: "Postings are only allowed into open periods." Books reconcile hone ke baad periods <b>order mein</b> ${ui('Close')} karein; ${ui('Add next financial year')} se agla saal. Closed period mein posting reject hoti hai.`,
  `${nav('Accounting', 'Bank & cash')}: bank/cash accounts — account numbers masked dikhte hain.`,
  `${nav('Accounting', 'Tax rates')}: sales/purchase tax rates (maslan Standard 15%, Zero 0%) jo invoice/bill lines par lagte hain.`,
])}
${fig('owner-finance-periods', 'Financial periods — har mahina Open/Closed; Close button.')}
${h3('Financial statements')}
<p>Profit &amp; loss, Balance sheet aur Trial balance ${nav('Insights', 'Reports & exports')} se chalayein (7.13) — wahan se CSV/Excel/PDF export bhi hota hai. Yeh sab posted general-ledger entries se bante hain.</p>
${fig('owner-report-bs', 'Reports &amp; exports → Balance sheet — assets, liabilities aur equity, as of date.')}
${fig('owner-report-tb', 'Trial balance — har account ke debit/credit totals; dono columns barabar hone chahiye.')}
${note(`${nav('Accounting', 'Financial reports')} page par bhi ${ui('Profit & loss')}, ${ui('Balance sheet')}, ${ui('Trial balance')} aur ${ui('Cash flow')} tabs hain, ${ui('Compare')} switch (pichle period se muqabla) aur ${ui('Print')} button ke sath.`)}

${h2('daily-arap', '7.8 Receivables & Payables')}
${h3('Customer invoice ka safar')}
${flow('Draft', 'Pending approval', 'Approved', 'Partially paid', 'Paid')}
${steps([
  `${nav('Receivables', 'Customer invoices')} → ${ui('New invoice')}: customer, dates, lines (product/description, account, qty, price, discount, tax).`,
  `Draft save hota hai. ${ui('Submit')} se approval ke liye bhejein ("It stays editable until an approver posts it.").`,
  `Approver ${ui('Approve')} karta hai: "Approving posts … to the general ledger (Dr receivables / Cr revenue &amp; tax). It can then only be voided, not edited."`,
  `Customer paisa de to ${ui('Record receipt')} — drawer mein bank account, date, method, amount aur allocation.`,
  `${ui('PDF')} button se invoice ka PDF.`,
])}
${fig('owner-finance-invoices', 'Invoices list — outstanding receivables, overdue, DSO aur average days to pay cards.')}
${fig('owner-finance-invoice-pending', 'Pending approval invoice — Edit, Delete, PDF, Approve, Void. "Journal: Not posted".')}
${fig('owner-finance-record-receipt-drawer', 'Record receipt drawer — customer, bank/cash account, method, amount; "Allocate to open invoices" aur Auto-allocate oldest first. (Submit nahi kiya gaya.)')}
${warn(`<b>Void ka qaida:</b> jis invoice/bill par payments allocate hon, woh seedha void nahi hoti — "has payments allocated; void those payments first." Pehle ${nav('Receivables', 'Receipts')} (ya Payments) mein payment void karein, phir document void karein. Approved document void karne par system reversing journal entry post karta hai.`)}
${h3('Supplier bills, payments, expense claims')}
${bullets([
  `${nav('Payables', 'Supplier bills')}: wohi flow — approve karne par "Dr expense &amp; tax / Cr payables" post hota hai. ${ui('Record payment')} se supplier ko adaigi.`,
  `${nav('Payables', 'Payments')} aur ${nav('Receivables', 'Receipts')}: recorded payments posted hote hain; "correct them by voiding".`,
  `${nav('Payables', 'Expense claims')}: employee ke apni jeb se kharche. Approve par "Dr expenses &amp; tax / Cr accrued liabilities" — employee ko payable.`,
])}
${fig('owner-finance-bills', 'Supplier bills — outstanding payables, overdue, expense claims payable.')}
${fig('owner-finance-expense-detail', 'Submitted expense claim — lines, receipts, Reject / Approve.')}
${h3('Aging & statements')}
<p>${ui('Aging')} page (invoices/bills page se link) receivables/payables ko din ke hisaab se buckets mein dikhata hai: Current, 1–30, 31–60, 61–90, &gt; 90 days, aur ${ui('Customer statement')} tab.</p>
${fig('owner-finance-aging', 'Aging &amp; statements — Receivables (AR), Payables (AP), Customer statement.')}

${h2('daily-sales', '7.9 Sales & CRM')}
${flow('Lead', 'Opportunity (pipeline)', 'Quotation', 'Sales order', 'Delivery', 'Invoice')}
${bullets([
  `${nav('Sales & CRM', 'Customers')}: ${ui('New customer')}; detail page par ${ui('Overview')}, ${ui('Contacts')}, ${ui('Communications')}, ${ui('Quotations')}, ${ui('Orders')} tabs aur AR balance. ${ui('List')} / ${ui('Map')} view.`,
  `${nav('Sales & CRM', 'Leads')}: prospects — status New → Contacted → Qualified → Converted; ${ui('Convert lead')} se customer.`,
  `${nav('Sales & CRM', 'Pipeline')}: opportunities ka board (New, Qualified, Proposal, Negotiation, Won, Lost). Cards drag karein ya card ke menu se keyboard se move karein.`,
  `${nav('Sales & CRM', 'Quotations')}: Draft → Sent → Accepted / Rejected / Expired → Converted. ${ui('Convert to order')} se sales order.`,
  `${nav('Sales & CRM', 'Sales orders')}: Draft → Confirmed → Approved → Partially delivered → Delivered. Confirm hone ke baad lines read-only. ${ui('Create delivery')} se shipment.`,
  `${nav('Sales & CRM', 'Deliveries')}: carrier aur tracking number haath se darj hote hain — live GPS nahi.`,
])}
${fig('owner-sales-customer-detail', 'Customer detail — quotations, orders, open opportunities, AR balance aur details.')}
${fig('owner-sales-pipeline', 'Pipeline board — open pipeline, weighted value, win rate; stages mein deals.')}
${fig('owner-sales-quotations', 'Quotations list — status aur customer filters.')}
${fig('owner-sales-order-detail', 'Sales order (Partially delivered) — har line ki delivered qty, Create delivery button.')}
${fig('owner-sales-order-approve', 'Confirmed sales order — quotation se bana; Approve / Cancel order.')}
${fig('owner-sales-reports', 'Sales reports — quotations vs orders, conversion rate, rep/customer/month-wise.')}

${h2('daily-procurement', '7.10 Procurement')}
${flow('Requisition', 'Approval', 'Purchase order', 'PO approval', 'Goods receipt', 'Supplier bill')}
${steps([
  `<b>Requisition:</b> ${nav('Procurement', 'Requisitions')} → ${ui('New requisition')} (items, qty, estimated price, needed by, priority) → ${ui('Submit')}. Requester apni requisition approve nahi kar sakta.`,
  `<b>Approval:</b> manager detail page par ${ui('Approve')} / ${ui('Reject')}.`,
  `<b>Purchase order:</b> approved requisition se ${ui('Create purchase order')} — ya ${nav('Procurement', 'Purchase orders')} → ${ui('New purchase order')}. Draft → ${ui('Submit')} → ${ui('Approve')}.`,
  `<b>Goods receipt:</b> maal aaye to PO par ${ui('Receive goods')} (ya ${nav('Procurement', 'Goods receipts')} → ${ui('Receive goods')}): received/rejected qty, batch, expiry. Confirm karne par stock barhta hai.`,
  `Kharab maal: receipt ya PO se ${ui('Return goods')}. Sab mukammal ho to PO ${ui('Close')}.`,
  `<b>Bill:</b> supplier ki invoice ${nav('Payables', 'Supplier bills')} mein darj karein aur approve karein.`,
])}
${fig('owner-proc-requisition-detail', 'Submitted requisition — lines, estimated total, progress; Reject / Approve.')}
${fig('owner-proc-order-detail', 'Received purchase order — requisition se bana; received/returned/remaining qty; Close aur Return goods.')}
${fig('owner-proc-receipt-detail', 'Goods receipt — ordered vs received vs rejected, unit cost, delivery note.')}
${fig('owner-proc-suppliers', 'Suppliers — terms, rating, approval status.')}
${fig('owner-proc-reports', 'Procurement reports — committed spend by supplier, status summary, supplier performance.')}

${h2('daily-inventory', '7.11 Inventory')}
<p>Stock ka asal record <b>stock movements</b> hai — har receipt, issue, transfer aur adjustment aik movement banata hai jo badla nahi ja sakta ("The immutable stock ledger"). Product ki on-hand quantity isi se nikalti hai. Valuation <b>weighted (moving) average cost</b> par hoti hai.</p>
${bullets([
  `${nav('Inventory', 'Products')}: ${ui('New product')}; ${ui('Categories & units')} se categories aur units of measure.`,
  `${nav('Inventory', 'Stock levels')}: product × warehouse — on hand, reserved, available, reorder level, avg cost, value. ${ui('Receive')} / ${ui('Issue')} buttons; ${ui('Below reorder level')} filter.`,
  `${nav('Inventory', 'Movements')}: poori history type aur warehouse filter ke sath.`,
  `${nav('Inventory', 'Transfers')}: ${ui('New transfer')} → ${ui('Ship')} (source se minus) → ${ui('Receive')} (destination mein plus). Status Draft → In transit → Received.`,
  `${nav('Inventory', 'Adjustments')}: count, damage, loss/theft, correction, expiry ke baad farq — Draft → ${ui('Post')}. Posting difference stock ledger (aur GL) mein likhti hai.`,
  `${nav('Inventory', 'Warehouses')}: warehouses aur un ke andar zones/aisles/racks/bins.`,
])}
${fig('owner-inv-product-detail', 'Product detail — warehouse-wise stock, movement history, Receive / Issue.')}
${fig('owner-inv-stock', 'Stock levels — on hand, reserved, available, reorder at, avg cost, value.')}
${fig('owner-inv-movements', 'Stock movements — immutable ledger; har movement ka type, qty, unit cost aur balance after.')}
${fig('owner-inv-transfer-detail', 'Received transfer — Main Warehouse se Chicago Warehouse; shipped/received dates.')}
${fig('owner-inv-reports', 'Inventory reports — valuation by warehouse, movement summary.')}
${warn('Stock ko kabhi "seedha" theek karne ki koshish na karein — hamesha Adjustment banayein taake wajah, kaun aur kab record ho. Low-stock alerts roz scheduler ke zariye inventory users ko jate hain.')}

${h2('daily-projects', '7.12 Projects & Tasks')}
${steps([
  `${ui('Projects')} kholein → ${ui('Portfolio')} (saare projects, cards/list) ya ${ui('My tasks')}.`,
  `${ui('New project')}: naam, manager, dates, budget, priority.`,
  `Project page tabs: ${ui('Overview')}, ${ui('Board')}, ${ui('List')}, ${ui('Milestones')}, ${ui('Members')}, ${ui('Time')}, ${ui('Expenses')}, ${ui('Files')}.`,
  `${ui('New task')} se task; Board par cards columns (To do, In progress, In review, Done, Blocked) mein drag karein.`,
  'Task par click se drawer: status, priority, assignee, milestone, dates, estimate, labels, subtasks, comments.',
  `${ui('Log time')} se kaam ke ghante darj karein (har shakhs sirf apna time log karta hai).`,
])}
${fig('owner-project-overview', 'Project overview — completion %, hours vs estimate, budget used, overdue tasks, milestones.')}
${fig('owner-project-board', 'Project board — status columns aur task cards.')}
${fig('owner-project-task-drawer', 'Task drawer — title, status, priority, assignee, milestone, dates, estimate, labels, subtasks; Save changes.')}

${h2('daily-documents', '7.13 Documents, Reports & Exports, Search')}
${h3('Documents')}
<p>${ui('Documents')} mein company files folders (Contracts, HR, Policies…) mein rakhi jati hain — versions, visibility (Company / Restricted / Private), owner aur <b>retain until</b> date ke sath. ${ui('Upload')} se file (PDF, images, Office, CSV, TXT; default max 10 MB).</p>
${fig('owner-documents', 'Documents — folders, category, visibility, owner, retention date.')}
${h3('Reports & exports')}
${steps([
  `${nav('Insights', 'Reports & exports')} kholein. Left mein reports groups mein hain: Finance, Sales, Procurement, Inventory, HR, Projects, Audit.`,
  'Report par click karein (maslan "Profit and loss").',
  `Parameters (From/To, Branch…) set karke ${ui('Run report')} — neeche preview table.`,
  `${ui('Export')} menu se CSV, Excel ya PDF. Chhoti export foran download; bari (2,000+ rows) background mein banti hai aur tayyar hone par notification aati hai — ${ui('My exports')} mein milti hai.`,
])}
${fig('owner-report-pl', 'Profit and loss report — period, branch, Run report aur Export; preview mein revenue, expenses, net profit.')}
${security('Har export audit log mein record hota hai. Export ke liye "Export reports" permission chahiye.')}
${h3('Global search')}
<p>Topbar ka ${ui('Search…')} box (ya ${c('/search')}) employees, customers, suppliers, products, documents aur transactions mein dhoondta hai — sirf woh jo aap ki permission mein hain. Kam az kam 2 characters likhein.</p>
${fig('owner-search', 'Search results — type-wise groups.')}

${h2('daily-settings', '7.14 Settings & Themes')}
${bullets([
  `${ui('My profile')}: naam aur phone. Sign-in email sirf administrator badal sakta hai.`,
  `${ui('Security')}: password change (dusre sessions sign out), two-factor (Chapter 4.4).`,
  `${ui('Notifications')}: har event ke liye In app / Email.`,
  `${ui('Appearance')}: Theme ${ui('Light')} / ${ui('Dark')} / ${ui('System')} — "Saved to your account and applied on every device". ${ui('High-contrast mode')} aur ${ui('Compact sidebar')}.`,
])}
${fig('owner-settings-appearance', 'Appearance — theme, high-contrast mode, compact sidebar.')}
${fig('owner-dark-dashboard', 'Dark mode mein dashboard — Account menu se theme Dark.')}
${tip('Animations operating system ki "reduce motion" setting ko khud follow karti hain.')}
`,
};
