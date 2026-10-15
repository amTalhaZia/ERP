import { h2, ui, fig, figPair, steps, bullets, tip } from './lib.mjs';

export default {
  id: 'ch-mobile',
  title: 'Mobile use',
  subtitle: 'Phone ke browser se ERP chalana — koi alag app install karne ki zaroorat nahi.',
  render: () => `
${h2('mob-basics', '9.1 Phone par ERP kholna')}
<p class="lead">ERP <b>responsive</b> hai: phone ke browser (Chrome, Edge, Safari) mein wohi address kholein jo computer par kholte hain. Screen chhoti hone par layout khud adjust hota hai — sidebar chhup jata hai aur menu button se khulta hai.</p>
${steps([
  'Phone ke browser mein ERP ka address kholein aur email/password se login karein (2FA on ho to authenticator app ka code).',
  'Upar left mein menu button (☰, "Open menu") dabayein — sidebar drawer khulta hai.',
  'Module chunein; drawer khud band ho jata hai. Band karne ke liye ✕ ("Close menu") bhi hai.',
  'Lambi tables ko left/right swipe karke scroll karein.',
])}
${figPair(['mobile-dashboard', 'Phone par dashboard — sidebar band, KPI cards aik column mein.'], ['mobile-menu-open', 'Menu drawer khula — wohi sections aur modules.'])}
${figPair(['mobile-approvals', 'Approvals inbox phone par — chalte phirte approve/reject.'], ['mobile-invoices', 'Invoices list phone par.'])}

${h2('mob-tips', '9.2 Mobile tips')}
${bullets([
  'Roz ke chhote kaam — approvals, leave request, check-in, payslip dekhna — phone par aasaan hain.',
  'Bari data entry (journal entries, invoices ki kai lines, payroll setup) ke liye computer behtar hai.',
  `Browser ka "Add to Home screen" option ERP ko app ki tarah icon de deta hai.`,
  `Dark mode raat ko aankhon par kam bojh dalta hai (Account menu → ${ui('Dark')}).`,
])}
${tip('Public ya doosre ke phone par login karein to kaam ke baad <b>Sign out</b> zaroor karein, aur "Keep me signed in" tick na karein.')}
`,
};
