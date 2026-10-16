import { h2, h3, ui, nav, c, bullets, tip, note, table } from '../guide/lib.mjs';

export default {
  id: 'lv-overview',
  title: 'Ek nazar mein — teen levels',
  subtitle: 'Mall ki misaal se poora nizaam 5 minute mein samjhein.',
  render: () => `
${h2('ov-q', '1.1 Aap ka sawal')}
<p class="lead">"3 types ke admin kyun hain, kaun kya karta hai, kaun kise banata hai, aur Platform ke andar 3 cheezein kya hain?" — yeh guide bilkul isi sawal ka jawab hai. Pehle aik choti si misaal, phir har level ki detail, aur aakhir mein woh sawal jo aam taur par zehan mein aate hain.</p>

${h2('ov-mall', '1.2 Mall ki misaal')}
<p>Apne ERP ko aik <b>shopping mall</b> samjhein. Mall mein kai dukaanein hain; har dukaan ka apna maalik hai, aur har dukaan ka apna staff.</p>
<div class="mall">
  <div class="m l1"><div class="ic">🏢</div><h4>Mall ka maalik</h4><div class="eq">= Platform Owner / Platform Admin</div><p>Building chalata hai: nayi dukaan kholta hai, band karta hai, bijli-pani (server) dekhta hai. Lekin <b>kisi dukaan ke galle (cash) ya register mein jhaank nahi sakta</b>.</p></div>
  <div class="m l2"><div class="ic">🏪</div><h4>Dukaan ka maalik</h4><div class="eq">= Company Owner</div><p>Apni dukaan ka poora malik: staff rakhta/nikalta hai, har register dekh sakta hai, har faisla kar sakta hai — lekin sirf <b>apni</b> dukaan mein.</p></div>
  <div class="m l3"><div class="ic">🧑‍💼</div><h4>Dukaan ka manager &amp; staff</h4><div class="eq">= Company Admin + staff roles</div><p>Manager (Company Admin) roz ka intezam karta hai; baqi staff (HR, Accountant, Sales…) sirf apna apna kaam. Har aik ko utni hi chaabiyan milti hain jitni kaam ke liye zaroori.</p></div>
</div>

${h2('ov-tree', '1.3 Hierarchy diagram')}
<div class="tree">
  <div class="tag">Level 1 — Platform (poori installation, sab companies)</div>
  <div class="lvl">
    <div class="node l0"><b>Platform Owner</b><span>platform.owner<br>server command se banta hai</span></div>
    <div class="node plat side"><b>Platform Admin</b><span>platform.admin<br>sirf Platform Owner banata hai</span></div>
  </div>
  <div class="link"></div>
  <div class="tag">Company banate waqt owner ko email invitation</div>
  <div class="link"></div>
  <div class="tag">Level 2 — har company ka malik</div>
  <div class="lvl">
    <div class="node l1"><b>Company Owner</b><span>company.owner · Company A</span></div>
    <div class="node l1"><b>Company Owner</b><span>company.owner · Company B</span></div>
  </div>
  <div class="link"></div>
  <div class="tag">Level 3 — company ke andar (Owner / Admin invite karte hain)</div>
  <div class="lvl">
    <div class="node l2"><b>Company Admin</b><span>company.admin · optional deputy<br>sab kuch, siwaye payroll processing</span></div>
  </div>
  <div class="link"></div>
  <div class="lvl">
    <div class="node"><b>HR Manager</b><span>HR + payroll</span></div>
    <div class="node"><b>Accountant</b><span>finance</span></div>
    <div class="node"><b>Sales Manager</b><span>CRM + sales</span></div>
    <div class="node"><b>Inventory Mgr</b><span>stock</span></div>
  </div>
  <div class="lvl" style="margin-top:2mm">
    <div class="node"><b>Procurement</b><span>purchasing</span></div>
    <div class="node"><b>Dept Manager</b><span>team approvals</span></div>
    <div class="node side"><b>Auditor</b><span>sirf dekhna</span></div>
    <div class="node"><b>Employee</b><span>self-service</span></div>
  </div>
</div>
${note('Level 1 aur Level 2 ke darmiyan aik <b>deewar</b> hai: Platform wale companies ka intezam karte hain, company ka business data (salaries, invoices, customers) nahi dekhte — jab tak woh khud us company ke member na banaye jayen.')}

${h2('ov-summary', '1.4 Aik page ka khulasa')}
${table(['', '<span class="lvl-pill p1">Level 1</span> Platform Owner / Admin', '<span class="lvl-pill">Level 2</span> Company Owner', '<span class="lvl-pill p3">Level 3</span> Company Admin'], [
  ['<b>Kaam</b>', 'System chalana: companies, accounts, server ki sehat', 'Apni company ka business chalana — sab kuch', 'Owner ka deputy — roz ka admin kaam'],
  ['<b>Kahan nazar aata hai</b>', `Sidebar mein ${ui('PLATFORM')}: ${ui('Companies')}, ${ui('Platform users')}, ${ui('System')}`, 'Poora company menu (Dashboard, HR, Finance, Users &amp; roles…)', 'Owner jaisa poora menu'],
  ['<b>Kaun banata hai</b>', `Pehla Owner: server par ${c('php artisan erp:create-admin')}. Platform Admin: sirf Platform Owner`, 'Platform user — New company banate waqt owner ka naam/email deta hai', 'Company Owner (ya koi aur jis ke paas user manage karne ki permission ho)'],
  ['<b>Company data</b>', '<span class="no">✗</span> Nahi dekh sakta (jab tak member na ho)', '<span class="yes">✓</span> Sab kuch (sirf apni company)', '<span class="yes">✓</span> Sab kuch, siwaye payroll chalane ke'],
  ['<b>Kis ko bana sakta hai</b>', 'Companies + un ke Owners; Platform Admins (sirf Owner)', 'Koi bhi role, doosra Owner bhi', 'Staff roles — lekin Owner nahi, aur apne se zyada taqat wala role nahi'],
  ['<b>Role badal sakte hain?</b>', 'Platform role sirf Platform Owner badal sakta hai', 'Company Owner role <b>Locked</b> — koi edit/delete nahi kar sakta', 'Apne hi role ki permissions khud nahi badal sakta'],
  ['<b>Demo login</b>', c('platform@erp.test'), c('owner@northwind.test'), c('admin@northwind.test')],
], { caption: 'Teen levels — khulasa' })}
${tip('Agar aap ka sirf <b>aik hi business</b> hai to aap aik hi account se Platform Owner aur Company Owner dono ban sakte hain — tafseel chapter 2.3 mein.')}

${h2('ov-three', '1.5 "Platform ke andar 3 cheezein" — mukhtasar')}
${bullets([
  `<b>${ui('Companies')}</b> — sab companies ki list. Nayi company banana (owner ke sath), company ko activate/deactivate karna, aur inactive company ko delete (archive) karna.`,
  `<b>${ui('Platform users')}</b> — poore system ke tamam accounts. Kisi account ko band (deactivate) ya wapas chalu karna; aur Platform Owner ke liye: kisi ko Platform Admin banana ya hatana.`,
  `<b>${ui('System')}</b> — server ki "sehat ki report": app/PHP/database versions, mail/queue jaisi services, companies/users ki ginti, disk space, database tables ka size. Yeh sirf <b>dekhne</b> ke liye hai.`,
])}
<p>Teeno ki poori tafseel screenshots ke sath chapter 3 mein hai.</p>
`,
};
