import { h2, ui, nav, c, checklist, table, tip, warn } from '../guide/lib.mjs';

export default {
  id: 'acc-checklist',
  title: 'Best-practice checklist',
  subtitle: 'Har mahine aur har naye shakhs ke waqt in points ko tick karein.',
  render: () => `
${h2('bp-setup', '10.1 Shuru mein (aik dafa)')}
${checklist([
  `Platform Owner ${c('erp:create-admin')} se bana, 2FA on, ${c('ERP_ALLOW_ADMIN_BOOTSTRAP=false')} wapas.`,
  'Platform Admin sirf zaroorat ho to — 1 ya 2 bharosemand IT log.',
  'Har company ka sirf aik (zyada se zyada do) Company Owner.',
  'Roz ke admin kaam ke liye Company Administrator — Owner ka login roz istemal na ho.',
  'Owners, Admins, Accountants aur HR — sab ka 2FA on.',
  'Production par koi demo company ya demo user nahi.',
])}
${h2('bp-new', '10.2 Naya shakhs aaye to')}
${checklist([
  'Apna personal account banayein — kabhi shared login nahi.',
  '<b>Least privilege:</b> sirf woh role jo us ke kaam ke liye zaroori ho; zyada access baad mein dena aasaan hai, wapas lena mushkil.',
  'Branch ka kaam ho to "Access to all branches" off karke sirf us ki branch.',
  'Employee ho to HR us ka employee record bana kar "Linked user" mein login jore.',
  'Create aur approve aik hi shakhs ko na milein (maker–checker).',
])}
${h2('bp-monthly', '10.3 Har mahine review')}
${checklist([
  `${nav('Users & roles', 'Users')} mein "Last sign-in" dekhein — 60+ din se istemal na hone wale accounts inactive karein.`,
  'Har role ki users list dekhein — kahin ghair-zaroori Owner/Admin to nahi?',
  `${ui('Platform users')} mein 2FA "Off" walon ko follow up.`,
  `${nav('Insights', 'Audit log')} mein "Users" aur "Roles &amp; permissions" categories — koi anjaan tabdeeli?`,
  `${c('auth.permission_denied')} entries — koi baar baar ijazat ke baghair pages to nahi khol raha?`,
  'Custom roles ab bhi zaroori hain? Ghair-istemal roles delete karein (pehle users hatayein).',
])}
${h2('bp-leave', '10.4 Koi chhor kar jaye to (usi din)')}
${checklist([
  'Company membership "Active in this company" off ya "Remove from company".',
  'Agar woh kisi company mein nahi rahega to Platform users mein "Deactivate account".',
  'Us ke banaye hue pending kaam (drafts, approvals) kisi aur ko assign karein.',
  'HR mein employee record "Terminate" — record delete na karein (history ke liye).',
  'Shared passwords (agar kabhi diye hon) badal dein.',
])}
${h2('bp-auditor', '10.5 Bahar ke auditor ke liye')}
${table(['Qadam', 'Kya karein'], [
  ['1', `Usay ${ui('Invite user')} se "Read-Only Auditor" role dein — sab modules aur audit log dekh sakta hai, reports export kar sakta hai, kuch badal nahi sakta.`],
  ['2', 'Zaroorat ho to sirf mutalliqa branches tak mehdood karein.'],
  ['3', 'Audit khatam hote hi "Remove from company".'],
], { caption: 'External auditor' })}
${warn('Kabhi kisi auditor, consultant ya vendor ko Owner/Admin login share na karein. Har bahar wale ke liye alag account aur mehdood role.')}
${tip('Yaad rakhein: jis ke paas "Manage roles &amp; permissions" hai woh roles ki permissions badal sakta hai — is liye Company Administrator ko taqreeban Owner jitna bharosemand samjhein (chapter 4.3).')}
`,
};
