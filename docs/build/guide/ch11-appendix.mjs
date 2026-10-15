import { h2, c, table, warn, pre } from './lib.mjs';
import { catalogue } from './roles-data.mjs';

const DOMAINS = ['northwind.test', 'indus.test'];
const ROLES = [
  ['owner', 'Company Owner — sab kuch'],
  ['admin', 'Company Administrator — sab kuch siwaye payroll processing'],
  ['hr', 'HR Manager'],
  ['accountant', 'Accountant'],
  ['sales', 'Sales Manager'],
  ['inventory', 'Inventory Manager'],
  ['procurement', 'Procurement Officer'],
  ['manager', 'Department Manager'],
  ['employee', 'Employee (self-service)'],
  ['auditor', 'Read-Only Auditor'],
];

export default {
  id: 'ch-appendix',
  appendix: true,
  letter: 'A',
  title: 'Appendix — Commands, demo accounts & permission catalogue',
  render: () => `
${h2('app-commands', 'A.1 Command cheat-sheet')}
${table(['Kaam', 'Command', 'Kahan se'], [
  ['MySQL data folder banana (aik dafa)', c('.\\database\\local\\init.ps1'), 'Project root'],
  ['MySQL start (background)', c('.\\database\\local\\start.ps1 -Background'), 'Project root'],
  ['MySQL stop', c('.\\database\\local\\stop.ps1'), 'Project root'],
  ['App database + user banana', c('.\\database\\local\\create-app-db.ps1'), 'Project root'],
  ['Sab kuch start (DB + API + frontend)', c('.\\start-dev.ps1'), 'Project root'],
  ['PHP packages', c('composer install'), 'backend'],
  ['App key', c('php artisan key:generate'), 'backend'],
  ['DB connection check', c('php artisan db:check'), 'backend'],
  ['DB self-test', c('php artisan db:selftest'), 'backend'],
  ['Schema + permissions', c('php artisan migrate --seed'), 'backend'],
  ['Production migration', c('php artisan migrate --force'), 'backend'],
  ['Permission catalogue (prod)', c('php artisan db:seed --force'), 'backend'],
  ['Demo data (sirf dev)', c('php artisan db:seed --class=DemoSeeder'), 'backend'],
  ['Pehla platform owner', c('php artisan erp:create-admin'), 'backend'],
  ['Pehla owner (production)', c('ERP_ALLOW_ADMIN_BOOTSTRAP=true php artisan erp:create-admin --force'), 'backend'],
  ['API server (dev)', c('php artisan serve'), 'backend'],
  ['Queue worker', c('php artisan queue:work --tries=3'), 'backend'],
  ['Scheduler (dev)', c('php artisan schedule:work'), 'backend'],
  ['Failed jobs', c('php artisan queue:failed'), 'backend'],
  ['Retention cleanup preview', c('php artisan erp:prune --dry-run'), 'backend'],
  ['Low-stock check (manual)', c('php artisan inventory:check-reorder'), 'backend'],
  ['Config cache (prod)', c('php artisan config:cache route:cache event:cache'), 'backend'],
  ['Cache saaf karna', c('php artisan optimize:clear'), 'backend'],
  ['Backend tests', c('php artisan test'), 'backend'],
  ['Frontend packages', c('npm install'), 'frontend'],
  ['Frontend dev server', c('npm run dev'), 'frontend'],
  ['Frontend production build', c('npm run build'), 'frontend'],
  ['Is guide ke screenshots', c('npm run screenshots'), 'docs\\build'],
  ['Is guide ka PDF', c('npm run guide'), 'docs\\build'],
], { caption: 'Commands', compact: true, className: 'long' })}
${warn(`Production par <b>kabhi nahi</b>: ${c('migrate:fresh')}, ${c('migrate:reset')}, ${c('migrate:rollback')}, ${c('db:wipe')}, ${c('DemoSeeder')}.`)}

${h2('app-demo', 'A.2 Demo accounts')}
<div class="dev-only">DEVELOPMENT ONLY</div>
<p>Yeh accounts sirf demo data (${c('DemoSeeder')}) ke sath bante hain. Is installation mein in sab ka password ${c('password')} hai (${c('DEMO_PASSWORD')} se set). Production par yeh accounts <b>hargiz mojood nahi hone chahiyein</b>.</p>
${table(['Login', 'Role', 'Company'], [
  [c('platform@erp.test'), 'Platform owner (companies manage karta hai; company data nahi dekhta)', '—'],
  ...DOMAINS.flatMap((d) => ROLES.map(([u, r]) => [c(`${u}@${d}`), r, d === 'northwind.test' ? 'Northwind Trading (Demo) · USD' : 'Indus Manufacturing (Demo) · PKR'])),
], { caption: 'Demo logins — password: password', compact: true, className: 'long' })}

${h2('app-perms', 'A.3 Permission catalogue')}
<p>Roles editor mein har checkbox ke neeche yeh codes nazar aate hain. Platform permissions sirf platform staff ke liye hain aur company roles mein nahi aatin.</p>
${table(['Module', 'Permission code', 'Matlab'], Object.entries(catalogue).flatMap(([m, perms]) => Object.entries(perms).map(([slug, d]) => [m, c(slug), d])), { caption: 'Tamam permissions', compact: true, className: 'long' })}
`,
};
