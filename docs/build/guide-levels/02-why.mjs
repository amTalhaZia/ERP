import { h2, ui, c, steps, bullets, tip, note, warn, security, table } from '../guide/lib.mjs';

export default {
  id: 'lv-why',
  title: 'Teen levels kyun? 2 kyun nahi?',
  subtitle: 'System ko "multi-company" banaya gaya hai — is liye aik level business se upar hai.',
  render: () => `
${h2('why-multi', '2.1 Asal wajah: aik system, kai companies')}
<p class="lead">Yeh ERP shuru se is tarah bana hai ke <b>aik hi installation</b> (aik server, aik database) par <b>kai alag companies</b> chal sakein — jaise aik group ki do companies, ya aap kal ko kisi doosre business ko bhi yahi system de dein. Har company ka data bilkul alag rehta hai.</p>
<p>Jab kai companies hon to do bilkul alag kaam samne aate hain:</p>
${table(['Kaam', 'Kis ka', 'Misaal'], [
  ['<b>System chalana</b>', 'Platform (Level 1)', 'Nayi company kholna, kisi company ko band karna, kisi chhode hue account ko block karna, server ki disk space dekhna'],
  ['<b>Business chalana</b>', 'Company (Level 2 aur 3)', 'Employees, salary, invoices, stock, customers, approvals'],
], { caption: 'Do alag kaam' })}
<p>Agar sirf 2 level hote (maslan "Super admin" aur "Staff"), to jo shakhs nayi company banata hai, woh khud-ba-khud <b>har company ka sara data</b> bhi dekh leta — Company A ka admin Company B ki salaries dekh leta. Teen levels is cheez ko rokte hain:</p>
${bullets([
  '<b>Data privacy between companies</b> — Company A ka koi bhi shakhs Company B ka kuch nahi dekh sakta. Har company ka Owner sirf apni company ka malik hai.',
  '<b>Platform ≠ business</b> — Platform staff system chalate hain magar company ka business data <b>nahi</b> dekhte ("Platform staff cannot see company data unless they are a member of that company" — Companies page par yahi likha hai).',
  '<b>Owner ka bojh kam</b> — Level 3 (Company Admin) is liye hai ke roz-marrah ka kaam (users add karna, settings) Owner ke account ke baghair ho sake.',
])}
${security('Yeh rule sirf menu chhupane se nahi, <b>server</b> par lagu hai. Platform staff agar company ka koi page ya API khud khol bhi lein to server permission check karta hai aur inkaar kar deta hai, kyun ke un ki kisi company mein koi role nahi.')}

${h2('why-company-level', '2.2 Company ke andar do level kyun (Owner + Admin)?')}
${bullets([
  `<b>Owner</b> = aakhri ikhtiyar. Us ka role ${ui('Locked')} hai — koi us ki permissions kam nahi kar sakta, koi us ko hata nahi sakta (siwaye doosre Owner ke).`,
  '<b>Admin</b> = bharosemand deputy jo taqreeban sab kuch kar sakta hai, magar kuch "khatarnak" cheezein nahi: payroll chalana/approve karna, kisi ko Owner banana, Owner ko hatana, ya apni hi taqat barhana.',
  'Is tarah agar Admin ka password chori bhi ho jaye to woh Owner ko bahar nahi nikal sakta aur salary run nahi kar sakta.',
])}
${note('Company Admin <b>optional</b> hai. Choti company mein Owner khud sab kar sakta hai; Admin tab banayein jab koi aur shakhs roz ka admin kaam sambhale.')}

${h2('why-single', '2.3 Sirf aik business hai? — "2 level" setup')}
<p>Agar aap ki sirf aik company hai aur aap hi malik hain, to teen levels aap ko bhaari lag sakte hain. Hal: <b>aik hi account</b> dono kaam kare.</p>
${steps([
  `Server par ${c('php artisan erp:create-admin')} se apna Platform Owner account banayein (apne asli email se).`,
  `Login karke ${ui('Companies')} → ${ui('New company')} kholein, company ki details bharein.`,
  `<b>Owner</b> section mein ${ui('Owner name')} aur ${ui('Owner email')} mein <b>apna hi naam aur wohi email</b> likhein jis se aap abhi login hain, phir ${ui('Create company')}.`,
  'System dekhta hai ke yeh email pehle se maujood hai — is liye naya account nahi banta, sirf aap ko us company ka Company Owner bana diya jata hai (koi password email nahi aati).',
  'Ab aap ke aik hi login mein dono cheezein hain: sidebar mein poora company menu <b>aur</b> neeche PLATFORM section.',
])}
<div class="flow"><span class="st">Aap (Platform Owner + Company Owner)</span><span class="ar">→</span><span class="st">(optional) Company Admin</span><span class="ar">→</span><span class="st">Staff</span></div>
${tip('Is setup mein amalan sirf <b>2 level</b> reh jate hain: <b>Aap (full)</b> → <b>Staff</b>. Platform wala hissa aap ko kabhi kabhar hi chahiye hoga (naya account block karna, system ki sehat dekhna).')}
${warn('Aisa account bohat taqatwar hai — is par <b>2FA zaroor on</b> karein aur password kisi ko na dein. Roz ka admin kaam kisi aur ko dena ho to us ke liye alag Company Admin banayein; apna login share na karein.')}
`,
};
