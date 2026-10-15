import { h2, ui, nav, c, qa, table } from './lib.mjs';

export default {
  id: 'ch-faq',
  title: 'Troubleshooting, FAQ & Glossary',
  subtitle: 'Aam sawalat ke jawab aur ERP ki istilahat ka aasaan matlab.',
  render: () => `
${h2('faq-login', '10.1 Login aur account')}
${qa('Password bhool gaya hoon. Kya karoon?', `<p>Login page par ${ui('Forgot password?')} dabayein, email likhein aur link ka intezar karein. Security ki wajah se screen hamesha aik jaisa jawab deti hai, chahe email registered ho ya na ho. Link na aaye to spam folder dekhein ya administrator se poochein (development mein link ${c('storage/logs/laravel.log')} mein hota hai).</p>`)}
${qa('"Too many attempts" aa raha hai.', '<p>Aik email se aik minute mein 5 se zyada ghalat koshishein hon to system thori der rok deta hai. 1 minute baad dobara try karein.</p>')}
${qa('Phone gum ho gaya, 2FA code nahi aa sakta.', '<p>2FA setup ke waqt mile <b>recovery codes</b> mein se aik use karein (har code aik dafa). Codes bhi na hon to administrator se rabta karein.</p>')}
${qa('Main aik se zyada companies mein kaam karta hoon — dusri company kaise kholoon?', '<p>Topbar mein company ke naam (company switcher) par click karke dusri company chunein. Har company mein aap ka role alag ho sakta hai.</p>')}
${qa('Kuch der baad khud logout ho jata hoon.', `<p>Idle session ${c('SESSION_LIFETIME')} (default 120 minute) ke baad khatam hota hai. Password badalne par bhi baqi devices se logout hota hai. Administrator ne deactivate kiya ho to bhi foran logout hota hai.</p>`)}

${h2('faq-access', '10.2 Access aur permissions')}
${qa('Mujhe koi menu (jaise Payroll) nazar nahi aa raha.', '<p>Aap ke role mein us module ki permission nahi. Company administrator se kahein ke aap ko munasib role de (Chapter 5).</p>')}
${qa('Page par "Something went wrong — You don\'t have permission to perform this action" aata hai.', '<p>Yeh 403 hai: aap ke paas is page/action ki permission nahi. Agar zaroorat hai to administrator se role update karwayein.</p>')}
${qa('"Page not found" aa raha hai lekin record mojood hai.', '<p>Ho sakta hai record kisi <b>dusri company</b> ka ho (current company check karein), ya aap ki branch restriction se bahar ho, ya link purana ho.</p>')}
${qa('Main apni leave / expense / requisition approve kyun nahi kar sakta?', '<p>Maker–checker rule: jo request banaye woh khud approve nahi karta. Approvals inbox mein apni requests nazar hi nahi aatin. Payroll run banane wala bhi usay approve nahi kar sakta.</p>')}
${qa('"Your account isn\'t linked to an employee record".', `<p>HR ${nav('HR', 'Employees')} mein aap ka record khol kar ${ui('Edit')} → ${ui('Linked user')} mein aap ka login select kare. Tab check-in, leave aur payslips chalenge.</p>`)}

${h2('faq-finance', '10.3 Finance, payroll aur stock')}
${qa('Posted journal entry mein ghalti hai — edit kaise karoon?', `<p>Posted entries edit nahi hotin. Entry khol kar ${ui('Reverse')} karein, phir sahi entry nayi banayein.</p>`)}
${qa('Invoice void nahi ho rahi: "has payments allocated".', '<p>Pehle us invoice ki receipt (payment) void karein, phir invoice void karein.</p>')}
${qa('Journal entry save nahi ho rahi.', `<p>Debits aur credits barabar hone chahiyein (${ui('Balanced')} badge), har line par account ho, aur aik line mein debit aur credit dono na hon. Date wala financial period <b>open</b> hona chahiye.</p>`)}
${qa('Payslip mein tax zero aa raha hai, "No tax table".', `<p>${nav('Payroll', 'Salary structures')} → ${ui('Tax tables')} mein us period ke liye active tax table banayein, phir run ${ui('Recalculate')} karein (finalize se pehle).</p>`)}
${qa('Payroll run finalize ho gaya, ghalti nikli.', '<p>Finalized run badla nahi ja sakta. Accountant ko batayein — correction journal entry ya agle mahine adjustment se hogi.</p>')}
${qa('Stock ki quantity ghalat hai.', `<p>${nav('Inventory', 'Movements')} mein product ki history dekhein. Physical count ke baad farq ho to ${nav('Inventory', 'Adjustments')} se adjustment bana kar post karein — wajah (Stock count, Damage…) zaroor chunein.</p>`)}
${qa('Export "queued" hai, file nahi aayi.', `<p>Bari exports background mein banti hain; tayyar hone par notification aati hai aur ${ui('My exports')} mein milti hai. Agar der tak na aaye to IT se queue worker (${c('php artisan queue:work')}) check karwayein.</p>`)}
${qa('Map par tiles nazar nahi aa rahe.', '<p>Map OpenStreetMap ke tiles internet se laata hai — internet connection check karein. Map par sirf woh locations aati hain jin ke coordinates saved hon.</p>')}

${h2('faq-it', '10.4 IT / server ke masail')}
${table(['Symptom', 'Pehla qadam'], [
  ['Login page hi nahi khulta', `Frontend chal raha hai? (${c('npm run dev')} window / ${c('http://localhost:3000')})`],
  ['Har page par network error', `API chal rahi hai? ${c('http://localhost:8000/api/v1/health')} kholein`],
  ['Database errors', `${c('php artisan db:check')} — Hint line parhein (Chapter 2.8)`],
  ['Emails nahi ja rahin', `${c('MAIL_*')} settings; dev mein ${c('MAIL_MAILER=log')} hai to emails log file mein hain`],
  ['Exports / notifications atak gaye', `Queue worker aur scheduler chal rahe hain? ${c('php artisan queue:failed')}`],
], { caption: 'IT checklist' })}

${h2('faq-glossary', '10.5 Glossary (istilahat)')}
${table(['English term', 'Aasaan matlab'], [
  ['Tenant / Company', 'ERP mein aik alag company jis ka data doosri companies se juda hai'],
  ['Branch', 'Company ka daftar / site / shakh'],
  ['Role', 'Permissions ka majmooa jo user ko diya jata hai'],
  ['Permission', 'Kisi aik kaam ki ijazat (jaise invoice approve karna)'],
  ['Least privilege', 'Sirf utni access jitni kaam ke liye zaroori'],
  ['Maker–checker', 'Banane wala aur approve karne wala alag log'],
  ['2FA (Two-factor authentication)', 'Password ke sath phone app ka code bhi'],
  ['Audit log', 'Kis ne kab kya kiya — na mitne wala record'],
  ['General ledger (GL)', 'Company ki tamam accounting entries ka markazi register'],
  ['Chart of accounts', 'Tamam ledger accounts ki list (cash, bank, sales, rent…)'],
  ['Journal entry', 'Aik accounting entry jis mein debit = credit'],
  ['Debit / Credit', 'Double-entry ke do pehlu; har entry mein dono barabar'],
  ['Post / Posting', 'Entry ko ledger mein pakka darj karna'],
  ['Reverse / Reversing entry', 'Ghalat posted entry ko ulti entry se khatam karna'],
  ['Void', 'Document ko mansookh karna (posting reverse ho jati hai)'],
  ['Financial period', 'Accounting ka mahina; close hone par us mein posting band'],
  ['Receivables (AR)', 'Customers se lena baqi'],
  ['Payables (AP)', 'Suppliers / employees ko dena baqi'],
  ['Aging', 'Baqaya raqam ko kitne din purani hai, us hisaab se buckets'],
  ['DSO', 'Days sales outstanding — customers ausatan kitne din mein paisa dete hain'],
  ['Invoice', 'Customer ko bheja gaya bill'],
  ['Bill', 'Supplier se aaya hua bill'],
  ['Receipt / Payment', 'Paisa aana / paisa jana'],
  ['Expense claim', 'Employee ka apni jeb se kiya kharcha wapas lene ki darkhwast'],
  ['Payroll run', 'Aik mahine ki tamam salaries ka hisaab'],
  ['Payslip', 'Employee ki tankhwah ki parchi'],
  ['Pay component', 'Salary ka hissa: allowance, deduction, employer contribution'],
  ['Finalize', 'Payroll ko pakka karke ledger mein post karna (wapas nahi hota)'],
  ['Lead / Opportunity', 'Mumkina customer / mumkina deal'],
  ['Pipeline', 'Deals ke marahil ka board (New se Won/Lost tak)'],
  ['Quotation', 'Customer ko qeemat ki pesh-kash'],
  ['Sales order', 'Customer ka confirm order'],
  ['Requisition', 'Andar ki darkhwast ke yeh cheez khareedni hai'],
  ['Purchase order (PO)', 'Supplier ko diya gaya khareed ka order'],
  ['Goods receipt (GRN)', 'Supplier se maal wusool karne ka record'],
  ['Stock movement', 'Stock ki har aamad/raft ka na badalne wala record'],
  ['Transfer', 'Aik warehouse se doosre mein stock bhejna'],
  ['Adjustment', 'Ginti ya nuqsan ke baad stock theek karna'],
  ['Reorder level', 'Woh miqdar jis se kam hone par dobara order karna chahiye'],
  ['Weighted average cost', 'Stock ki ausat qeemat jo har khareed ke sath update hoti hai'],
  ['Milestone', 'Project ka aham marhala'],
  ['Retention', 'Data kitni der rakha jayega'],
  ['Export', 'Report ko CSV / Excel / PDF file mein nikalna'],
], { caption: 'English → Hinglish', compact: true, className: 'long' })}
`,
};
