// Builds docs/Enterprise-ERP-API-Reference.pdf (Hinglish API reference) from docs/api/openapi.yaml.
// Usage: node build-api.mjs        (needs Microsoft Edge; no browser download)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import YAML from 'yaml';
import { chromium } from 'playwright-core';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const SPEC_PATH = path.resolve(HERE, '../api/openapi.yaml');
const HTML_OUT = path.resolve(HERE, 'api-reference.html');
const PDF_OUT = path.resolve(HERE, '../Enterprise-ERP-API-Reference.pdf');
const EDGE = 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const BASE = 'http://localhost:8000/api/v1';
const PROD = 'https://api.example.com/api/v1';

const spec = YAML.parse(fs.readFileSync(SPEC_PATH, 'utf8'));
const S = spec.components.schemas;
const P = spec.components.parameters;
const R = spec.components.responses;
const VERSION = spec.info.version;
const TODAY = new Date().toISOString().slice(0, 10);
const METHODS = ['get', 'post', 'put', 'patch', 'delete'];

// ───────────────────────── helpers ─────────────────────────
const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
const md = (t) => esc(t)
  .replace(/`([^`]+)`/g, '<code>$1</code>')
  .replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>');
const slug = (t) => String(t).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
const refName = (ref) => ref.split('/').pop();
const deref = (s) => (s && s.$ref ? S[refName(s.$ref)] ?? {} : s ?? {});
const isEnumSchema = (s) => s && Array.isArray(s.enum) && !s.properties;
const types = (s) => (Array.isArray(s.type) ? s.type : s.type ? [s.type] : []);
const nullable = (s) => types(s).includes('null') || (s.anyOf || s.oneOf || []).some((x) => x.type === 'null');
const opAnchor = (op) => `op-${op.operationId}`;

function mergeAllOf(s) {
  if (!s || !s.allOf) return s;
  const out = { type: 'object', properties: {}, required: [] };
  for (const part of s.allOf) {
    const p = mergeAllOf(deref(part));
    Object.assign(out.properties, p.properties || {});
    out.required.push(...(p.required || []));
  }
  return out;
}

/** Human type label (HTML) with links to appendix schemas/enums. */
function typeLabel(s, depth = 0) {
  if (!s) return 'any';
  if (s.$ref) {
    const n = refName(s.$ref);
    const t = S[n];
    if (isEnumSchema(t)) return `<a href="#enum-${n}">enum ${n}</a>`;
    if (n === 'Money' || n === 'Decimal') return `<a href="#schema-${n}">${n}</a> (string)`;
    return `<a href="#schema-${n}">${n}</a>`;
  }
  const alts = s.anyOf || s.oneOf;
  if (alts) {
    const parts = alts.filter((a) => a.type !== 'null').map((a) => typeLabel(a, depth + 1));
    return [...new Set(parts)].join(' | ') + (alts.some((a) => a.type === 'null') ? ' | null' : '');
  }
  if (s.allOf) return 'object';
  let ts = types(s);
  if (!ts.length) ts = s.properties ? ['object'] : s.enum ? ['string'] : ['any'];
  const main = ts.filter((t) => t !== 'null');
  let label = main.map((t) => {
    if (t === 'array') return (s.items && Object.keys(s.items).length ? typeLabel(s.items, depth + 1) : 'any') + '[]';
    if (t === 'string' && s.format) return `string(${s.format})`;
    return t;
  }).join(' | ') || 'null';
  if (ts.includes('null')) label += ' | null';
  return label;
}

function constraints(s, { withDesc = true } = {}) {
  const c = [];
  const d = deref(s);
  const enumVals = s.enum || (s.$ref && isEnumSchema(d) ? null : null);
  if (enumVals) c.push('values: ' + enumVals.map((v) => `<code>${esc(v)}</code>`).join(', '));
  if (s.$ref && isEnumSchema(d)) {
    const v = d.enum;
    c.push(v.length <= 8 ? 'values: ' + v.map((x) => `<code>${esc(x)}</code>`).join(', ') : `${v.length} values (Appendix B)`);
  }
  if (s.minLength != null) c.push(`minLength ${s.minLength}`);
  if (s.maxLength != null) c.push(`maxLength ${s.maxLength}`);
  if (s.minimum != null) c.push(`min ${s.minimum}`);
  if (s.maximum != null) c.push(`max ${s.maximum}`);
  if (s.minItems != null) c.push(`minItems ${s.minItems}`);
  if (s.maxItems != null) c.push(`maxItems ${s.maxItems}`);
  if (s.pattern) c.push(`pattern <code>${esc(s.pattern)}</code>`);
  if (s.default !== undefined) c.push(`default <code>${esc(JSON.stringify(s.default))}</code>`);
  if (withDesc && s.description) c.push(md(s.description));
  return c.join(' · ');
}

/** Flatten an object schema into rows; arrays of objects become name[].field. */
function flatten(schema, prefix = '', depth = 0, out = []) {
  const s = mergeAllOf(deref(schema));
  const req = new Set(s.required || []);
  for (const [name, raw] of Object.entries(s.properties || {})) {
    const full = prefix + name;
    out.push({ name: full, type: typeLabel(raw), required: req.has(name), notes: constraints(raw) });
    if (depth >= 3) continue;
    const r = deref(raw);
    const rt = types(raw.$ref ? r : raw);
    if (rt.includes('array') || raw.items) {
      const it = raw.items ? mergeAllOf(deref(raw.items)) : null;
      if (it && it.properties && !(raw.items.$ref)) flatten(raw.items, `${full}[].`, depth + 1, out);
    } else if (!raw.$ref && raw.properties) {
      flatten(raw, `${full}.`, depth + 1, out);
    }
  }
  return out;
}

// ───────────────────────── sample generator ─────────────────────────
function sample(name, raw, depth = 0) {
  let s = raw || {};
  if (s.$ref) {
    const n = refName(s.$ref);
    if (isEnumSchema(S[n])) return S[n].enum[0];
    if (n === 'Money' || n === 'Decimal') return '1250.0000';
    s = S[n];
  }
  if (s.anyOf || s.oneOf) return sample(name, (s.anyOf || s.oneOf).find((x) => x.type !== 'null') || {}, depth);
  s = mergeAllOf(s);
  if (s.default !== undefined && s.default !== null) return s.default;
  if (s.enum) return s.enum.find((v) => v !== null);
  const ts = types(s).filter((t) => t !== 'null');
  const n = (name || '').toLowerCase();
  const desc = s.description || '';
  if (ts.includes('array') || s.items) {
    if (depth > 3) return [];
    const it = s.items || {};
    return [sample(n.replace(/s$/, ''), it, depth + 1)];
  }
  if (ts.includes('object') || s.properties) {
    if (depth > 3 || !s.properties) return {};
    return sampleObject(s, depth + 1);
  }
  const isDecimal = /Decimal value/.test(desc) || (ts.includes('string') && ts.includes('number'));
  if (isDecimal) {
    if (/percent/.test(n)) return '10.0000';
    if (/quantity|qty/.test(n)) return '2.0000';
    if (/hours/.test(n)) return '7.5000';
    if (/rate/.test(n)) return '17.0000';
    return '1250.0000';
  }
  if (ts.includes('boolean')) return /^is_|active|enabled/.test(n) ? true : false;
  if (ts.includes('integer')) {
    if (/^to_.*_id$/.test(n)) return 2;
    if (/_id$|^id$/.test(n)) return 1;
    if (/year/.test(n)) return 2026;
    if (/month/.test(n)) return 10;
    return Math.max(s.minimum ?? 1, 1);
  }
  if (ts.includes('number')) return s.minimum ?? 1;
  // strings
  if (s.format === 'date') return /end|due|to$|expires/.test(n) ? '2026-11-14' : '2026-10-15';
  if (s.format === 'date-time') return '2026-10-15T09:30:00Z';
  if (s.format === 'email' || /email/.test(n)) return 'ali.raza@example.com';
  if (s.format === 'uri' || /url|website/.test(n)) return 'https://example.com';
  if (s.format === 'binary') return '<file>';
  if (/H:i/.test(desc) || /_time$/.test(n)) return /end/.test(n) ? '17:00' : '09:00';
  if (s.pattern === '^\\d{6}$') return '123456';
  if (/currency/.test(n)) return 'PKR';
  if (/country/.test(n)) return s.maxLength === 2 ? 'PK' : 'Pakistan';
  if (/timezone/.test(n)) return 'Asia/Karachi';
  if (/phone|mobile/.test(n)) return '+92 300 1234567';
  if (/password/.test(n)) return 'S3cure-Passw0rd';
  if (/colou?r/.test(n)) return '#4f46e5';
  if (/^code$|_code$|sku/.test(n)) return 'CODE-001';
  if (/first_name/.test(n)) return 'Ali';
  if (/last_name/.test(n)) return 'Raza';
  if (/^name$|_name$/.test(n)) return 'Sample name';
  if (/title|subject/.test(n)) return 'Sample title';
  if (/city/.test(n)) return 'Karachi';
  if (/address/.test(n)) return 'Shahrah-e-Faisal, Karachi';
  if (/description|notes?$|reason|comment|body|terms|message/.test(n)) return 'Sample text';
  if (/latitude/.test(n)) return '24.8607';
  if (/longitude/.test(n)) return '67.0011';
  let v = 'string';
  if (s.maxLength && v.length > s.maxLength) v = v.slice(0, s.maxLength);
  return v;
}
function sampleObject(s, depth) {
  const req = new Set(s.required || []);
  const props = Object.entries(s.properties || {});
  const chosen = props.length <= 10 ? props : [...props.filter(([k]) => req.has(k)), ...props.filter(([k]) => !req.has(k)).slice(0, Math.max(0, 8 - req.size))];
  const o = {};
  for (const [k, v] of chosen) {
    if (/Prohibited/.test(v.description || '')) continue;
    o[k] = sample(k, v, depth);
  }
  return o;
}

// ───────────────────────── operation model ─────────────────────────
const TAG_ORDER = ['Health', 'Auth', 'Company', 'Access', 'Notifications', 'HR', 'Payroll', 'Finance – Ledger',
  'Finance – Receivables', 'Sales', 'Procurement', 'Inventory', 'Projects', 'Documents', 'Dashboard', 'Approvals',
  'Reports', 'Search', 'Platform'];
for (const t of spec.tags) if (!TAG_ORDER.includes(t.name)) TAG_ORDER.push(t.name);

const TAG_INTRO = {
  'Health': 'Ye public endpoints server ki liveness aur database readiness check karte hain. Load balancer ya monitoring tools inhe bina login aur bina <code>X-Company-Id</code> ke call kar sakte hain.',
  'Auth': 'Login, 2FA, password management, session bootstrap aur profile/preferences yahan hain. Poora login flow (CSRF cookie → login → 2FA → session) "Shuruaat" chapter mein step-by-step samjhaya gaya hai. In routes ko tenant header ki zaroorat nahi hoti; company badalne ke liye <code>POST /auth/switch-company</code> use karein.',
  'Company': 'Active company ka profile aur logo, branches, departments (tree ke saath) aur company members. Ye sab requests <code>X-Company-Id</code> wali active company ke context mein chalti hain.',
  'Access': 'Company users, roles, permission catalogue aur audit trail. Roles ke zariye permission slugs users ko milte hain; audit logs append-only hain aur sirf parhe ja sakte hain.',
  'Notifications': 'In-app notifications aur har user ki notification preferences. User apni notifications list kar sakta hai, unread count le sakta hai, read mark ya delete kar sakta hai.',
  'HR': 'Employees, designations, attendance (check-in/out, bulk), shifts, leave types/balances/requests, holidays, employment contracts, employee documents aur performance reviews. Self-service endpoints (jaise <code>/employees/me</code>, <code>/leave-balances/mine</code>) logged-in employee ka apna data dete hain.',
  'Payroll': 'Pay components, tax tables, salary structures, employee salaries, payroll runs aur payslips. Payroll run ka status <code>draft → calculated → approved → finalized → paid</code> (ya <code>cancelled</code>) chalta hai, aur har qadam ke liye alag action endpoint hai. Employees apni payslips <code>/my-payslips</code> se dekhte hain.',
  'Finance – Ledger': 'Chart of accounts, financial years/periods, journal entries, tax rates, bank accounts aur ledger reports (trial balance, P&amp;L, balance sheet, cash flow, general ledger). Debits ≠ credits par <code>422 finance.unbalanced</code>, aur closed period ya posted entry badalne par <code>409 finance.period_closed</code> / <code>finance.immutable</code> milta hai.',
  'Finance – Receivables': 'Invoices, bills, payments, expense claims aur AR/AP reports (aging, receivables summary, customer statement). Documents draft → submit → approve flow follow karte hain, aur radd karne ke liye <code>void</code> action hai. Invoice PDF bhi yahan se download hoti hai.',
  'Sales': 'Customers (contacts, communications, map), CRM (leads, opportunities, pipeline stages, Kanban board), quotations, sales orders aur deliveries. Quotation ko <code>convert</code> kar ke sales order banaya ja sakta hai aur sales order se deliveries banti hain.',
  'Procurement': 'Suppliers (contacts, approval, summary), purchase requisitions, purchase orders, goods receipts aur purchase returns, saath mein spend aur supplier-performance reports. Approved requisition se <code>create-purchase-order</code> ke zariye PO banta hai.',
  'Inventory': 'Products, categories, units of measure, warehouses, storage locations, stock levels/movements, stock receive/issue, adjustments aur transfers. Agar outbound movement available stock se zyada ho to <code>409 inventory.insufficient_stock</code> milta hai (<code>details</code> mein figures hote hain).',
  'Projects': 'Projects, members, milestones, tasks (Kanban board, subtasks, move, comments), time entries aur project expenses. Overview aur summary endpoints dashboards ke liye aggregate data dete hain.',
  'Documents': 'Document library: folders, uploads (multipart), versions, share links aur downloads. Files private disk par rehti hain aur download sirf policy check ke baad stream hota hai.',
  'Dashboard': 'Role-aware dashboard widgets — response user ki permissions ke mutabiq banta hai.',
  'Approvals': 'Unified approval inbox — mukhtalif modules ki pending approvals ek jagah, aur unke counts.',
  'Reports': 'Report catalogue, JSON preview (pehli 500 rows tak) aur exports. Chhota export seedha file stream karta hai; bada export <code>202</code> ke saath ek export job deta hai jise <code>/report-exports</code> se track aur download kiya jata hai. Expire ho chuki file <code>410</code> deti hai.',
  'Search': 'Global search — query parameter <code>q</code> (2–100 characters) se active company ke modules mein dhoondta hai. Is par alag rate limit (60 requests/minute) lagta hai.',
  'Platform': 'Platform administration: companies, users, system info aur platform audit logs. Ye sirf platform staff ke liye hai — baqi sab ko <code>404</code> milta hai — aur in routes par koi tenant context (<code>X-Company-Id</code>) nahi hota.',
};

function cleanSummary(sum, op, method, p) {
  let t = String(sum || '').replace(/\s*\*\/\s*$/, '').trim();
  t = t.replace(/^(GET|POST|PUT|PATCH|DELETE)\s+\S+\s*(—\s*)?/, '').trim();
  if (!t) {
    const segs = p.split('/').filter((x) => x && !x.startsWith('{'));
    const last = segs[segs.length - 1].replace(/-/g, ' ');
    t = method === 'post' ? `Create ${resourceFromPath(p)} (from ${human(segs[0]).replace(/s$/, '')})` : last;
  }
  return t;
}

const human = (seg) => seg.replace(/[-_]/g, ' ');
function resourceFromPath(p, plural = false) {
  const segs = p.split('/').filter((x) => x && !x.startsWith('{'));
  let r = human(segs[segs.length - 1] || '');
  if (!plural) r = r.replace(/ies$/, 'y').replace(/(ss)$/, '$1').replace(/([^s])s$/, '$1');
  return r;
}

const VERB_HI = {
  approve: (r) => `${r} ko approve karta hai.`,
  reject: (r) => `${r} ko reject karta hai.`,
  submit: (r) => `${r} ko approval ke liye submit karta hai.`,
  cancel: (r) => `${r} ko cancel karta hai.`,
  void: (r) => `${r} ko void (radd) karta hai.`,
  confirm: (r) => `${r} ko confirm karta hai.`,
  ship: (r) => `${r} ko shipped mark karta hai.`,
  receive: (r) => `${r} ki receiving record karta hai.`,
  deliver: (r) => `${r} ko delivered mark karta hai.`,
  send: (r) => `${r} ko sent mark karta hai.`,
  close: (r) => `${r} ko close karta hai.`,
  reopen: (r) => `${r} ko dobara open karta hai.`,
  activate: (r) => `${r} ko activate karta hai.`,
  deactivate: (r) => `${r} ko deactivate karta hai.`,
  convert: (r) => `${r} ko convert karta hai.`,
  assign: (r) => `${r} ko kisi user ko assign karta hai.`,
  calculate: (r) => `${r} ka calculation chalata hai.`,
  finalize: (r) => `${r} ko finalize karta hai.`,
  pay: (r) => `${r} ko paid mark karta hai.`,
  terminate: (r) => `${r} ko terminate karta hai.`,
  reactivate: (r) => `${r} ko dobara active karta hai.`,
  acknowledge: (r) => `${r} ko acknowledge karta hai.`,
  accept: (r) => `${r} ko accept karta hai.`,
  post: (r) => `${r} ko post karta hai.`,
  reverse: (r) => `${r} ko reverse karta hai.`,
};

function successInfo(op) {
  const out = [];
  for (const [code, rr] of Object.entries(op.responses || {})) {
    if (!/^2/.test(code)) continue;
    const r = rr.$ref ? R[refName(rr.$ref)] : rr;
    out.push({ code, r, content: r.content || {} });
  }
  return out;
}
function isPaginated(op) {
  const ok = op.responses?.['200']?.content?.['application/json']?.schema;
  return !!(ok && ok.properties && ok.properties.meta && ok.properties.meta.$ref?.endsWith('PaginationMeta'));
}
function fileContentType(op) {
  const ok = op.responses?.['200']?.content;
  if (!ok) return null;
  const cts = Object.keys(ok).filter((c) => c !== 'application/json');
  return cts.length ? cts.join(', ') : null;
}

const OVERRIDE_HI = {
  health: 'Application ki liveness check — <code>status</code>, <code>app</code>, <code>environment</code> aur <code>time</code> deta hai.',
  healthDb: 'Database connectivity (readiness) check karta hai.',
  authLogin: 'Email/password se login karta hai (login flow ka step 1). 2FA on ho to <code>two_factor: true</code> aata hai aur <code>POST /auth/2fa/challenge</code> zaroori hai.',
  auth2faChallenge: 'Login ka step 2: pending challenge ke liye TOTP <code>code</code> ya <code>recovery_code</code> verify karta hai.',
  authPasswordEmail: 'Password reset link email karta hai; account ho ya na ho, hamesha <code>200</code> deta hai.',
  authPasswordReset: 'Email wale reset token se naya password set karta hai; user ke baqi sessions invalidate ho jate hain.',
  authLogout: 'Current session khatam karta hai (<code>204</code>).',
  authSession: 'Current user, company memberships, active company aur uske permission slugs deta hai — SPA isi se bootstrap hota hai.',
  authSwitchCompany: 'Active company badalta hai; membership server par verify hoti hai aur switch audit hota hai.',
  authPasswordUpdate: 'Logged-in user ka password badalta hai; baqi sessions invalidate ho jate hain.',
  authPasswordConfirm: 'Password dobara confirm karta hai taake <code>password.confirm</code> wale sensitive actions agle 15 minute tak chal sakein.',
  authPreferences: 'Logged-in user ki preferences update karta hai.',
  authProfile: 'Logged-in user ka profile update karta hai.',
  authVerificationSend: 'Email verification link dobara bhejta hai.',
  auth2faEnable: 'Naya pending 2FA secret generate karta hai; 2FA <code>/auth/2fa/confirm</code> ke baad hi active hota hai.',
  auth2faConfirm: 'TOTP code se pending 2FA setup confirm karta hai.',
  auth2faDisable: 'User ka 2FA band karta hai.',
  search: 'Active company ke modules mein global search karta hai (<code>q</code> zaroori).',
  dashboard: 'User ke role/permissions ke mutabiq dashboard widgets ka data deta hai.',
  branchesAll: 'Saari active branches ki halki list deta hai (selectors aur map ke liye, bina pagination).',
  departmentsTree: 'Departments ka poora tree deta hai (org chart aur selectors ke liye).',
  usersResendInvite: 'Invited user ko password-reset link dobara bhejta hai taake woh apna password set kar sake.',
  hrEmployeesDirectory: 'Saare employees ki halki list deta hai (selectors, org chart).',
  hrEmployeesOrgChart: 'Reporting-line tree deta hai; roots woh employees hain jin ka koi manager nahi.',
  hrAttendanceSummary: 'Ek period ke liye har employee ke attendance totals deta hai (<code>from</code>, <code>to</code>, <code>department_id</code>).',
  hrLeaveBalancesMine: 'Logged-in user ke apne leave balances deta hai (default: current year).',
  hrLeaveBalancesAdjust: 'Leave balance ki manual correction karta hai (<code>adjusted</code>, optional <code>entitled</code>, <code>carried_forward</code>).',
  hrPerformanceReviewsAcknowledge: 'Jis employee ka review hua, woh confirm karta hai ke usne review parh liya.',
  accountsTree: 'Chart of accounts ki poori hierarchy deta hai (tree view aur account pickers ke liye).',
  accountsSeedDefaults: 'Standard chart of accounts, current financial year aur tax rates seed karta hai (idempotent — dobara chalane se duplicate nahi bante).',
  accountsLedger: 'Ek account ka general ledger deta hai: opening balance aur running balance ke saath lines.',
  customersAll: 'Selectors ke liye customers ki halki list deta hai (<code>id</code>, <code>name</code>, <code>code</code>, <code>currency</code>, <code>payment_terms_days</code>).',
  customersMap: 'Woh active customers deta hai jin ke coordinates hain (map view ke liye).',
  pipelineStagesReorder: 'Pipeline stages ki nayi tarteeb save karta hai: <code>{ ids: [...] }</code> → positions 1..n.',
  opportunitiesBoard: 'Kanban payload deta hai: har stage (tarteeb se) apni opportunities aur totals ke saath.',
  opportunitiesStage: 'Opportunity ko doosri stage mein move karta hai; won/lost stages opportunity ko close kar deti hain.',
  salesOrdersConfirm: 'Sales order ko Draft → Confirmed karta hai; Inventory/AR ko <code>SalesOrderConfirmed</code> ke zariye khabar jati hai.',
  salesOrdersApprove: 'Sales order ko Confirmed → Approved karta hai.',
  salesOrdersCancel: 'Sales order cancel karta hai, basharte kuch deliver na hua ho; open deliveries bhi cancel ho jati hain.',
  suppliersAll: 'Selectors ke liye active suppliers ki halki list deta hai (bina pagination).',
  suppliersMap: 'Geo-coded active suppliers deta hai (map view ke liye).',
  suppliersApprove: 'Vendor approval: supplier ko purchasing ke liye approved mark karta hai (audited).',
  requisitionsCancel: 'Requester apni draft ya submitted requisition wapas le leta hai.',
  purchaseOrdersClose: 'Purchase order ko manually close karta hai — jab poora receive ho chuka ho ya supplier se mazeed kuch expected na ho.',
  purchaseOrdersSend: 'Approved order ko supplier ko "sent" mark karta hai (status nahi badalta).',
  productCategoriesTree: 'Product categories ka tree deta hai; sirf active categories, jab tak <code>include_inactive=1</code> na ho.',
  productsStock: 'Ek product ka stock har warehouse / location ke hisaab se deta hai.',
  productsMovements: 'Ek product ki stock movement history deta hai (paginated).',
  stockLevels: 'Stock levels deta hai: product × warehouse (× location).',
  stockMovements: 'Poori company ki stock movement history deta hai.',
  projectsOverview: 'Projects index page ke liye portfolio stats deta hai.',
  projectsSummary: 'Ek project ka progress dashboard deta hai.',
  projectsBoard: 'Kanban board deta hai: har status ka column, tarteeb wale top-level tasks ke saath.',
  notificationsUnreadCount: 'Logged-in user ki unread notifications ki ginti deta hai.',
  notificationsReadAll: 'User ki saari notifications ko read mark karta hai.',
  notificationsRead: 'Ek notification ko read mark karta hai.',
  hrEmployeesMe: 'Logged-in user ka apna employee record deta hai (self-service).',
  hrEmployeesPhoto: 'Employee ki photo upload karta hai (multipart).',
  hrEmployeeDocumentsDownload: 'Employee document ki file download karta hai.',
  hrEmploymentContractsDocument: 'Employment contract ke saath attached document ki file download karta hai.',
  hrAttendanceBulk: 'Ek saath kai attendance records darj karta hai (bulk).',
  hrAttendanceCheckIn: 'Logged-in user ka attendance check-in record karta hai.',
  hrAttendanceCheckOut: 'Logged-in user ka attendance check-out record karta hai.',
  hrAttendanceToday: 'Aaj ki attendance ka data deta hai.',
  hrLeaveBalancesGenerate: 'Leave balances generate karta hai (optional <code>year</code> ke liye).',
  hrLeaveRequestsAttachment: 'Leave request ke saath attached file download karta hai.',
  payrollPayrollRunsPayslips: 'Payroll run ki saari payslips deta hai.',
  payrollPayrollRunsExport: 'Payroll run ko file (CSV/XLSX) ki shakal mein export/download karta hai.',
  payrollPayslipsPdf: 'Payslip ki PDF download karta hai.',
  payrollMyPayslipsPdf: 'Logged-in employee ki apni payslip ki PDF download karta hai.',
  invoicesPdf: 'Invoice ki PDF download karta hai.',
  expenseClaimsLinesReceiptStore: 'Expense claim line ke liye receipt file upload karta hai (multipart).',
  expenseClaimsLinesReceiptShow: 'Expense claim line ki receipt file download karta hai.',
  customersSummary: 'Ek customer ka summary (aggregate data) deta hai.',
  salesPipelineSummary: 'Sales pipeline ka summary (aggregate) deta hai.',
  suppliersSummary: 'Ek supplier ka summary deta hai (aggregate data).',
  productsLowStock: 'Woh products deta hai jin ka stock low hai.',
  productsBatches: 'Ek product ke batches deta hai.',
  warehousesAll: 'Saare warehouses ki list deta hai (selectors ke liye, bina pagination).',
  stockReceive: 'Warehouse mein stock receive (inbound movement) darj karta hai.',
  stockIssue: 'Warehouse se stock issue (outbound movement) karta hai; stock kam ho to <code>409 inventory.insufficient_stock</code>.',
  projectsTimeSummary: 'Ek project ki time entries ka summary deta hai.',
  tasksMine: 'Logged-in user ko assign shuda tasks deta hai (saare projects se).',
  tasksMove: 'Task ko naye <code>status</code> / <code>position</code> par move karta hai (Kanban).',
  documentsDownload: 'Document ka version (default: current) private disk se stream karta hai; download audit hota hai.',
  documentsSharedDownload: 'Share link ke zariye document download karta hai.',
  documentsShareLink: 'Document ke liye share link banata hai.',
  approvalsCounts: 'Pending approvals ki counts deta hai.',
  approvalsIndex: 'Approval inbox — pending approvals ki list deta hai.',
  reportsIndex: 'Woh reports list karta hai jo current user chala sakta hai.',
  reportsShow: 'Report ka paginated JSON preview deta hai (pehli 500 rows tak).',
  reportsExport: 'Report export karta hai: chhota export seedha file (<code>200</code>), bada export <code>202</code> + export job.',
  reportExportsDownload: 'Mukammal export job ki file download karta hai; expired ho to <code>410</code>.',
  platformSystem: 'Platform/system ki maloomat deta hai (sirf platform staff).',
  platformCompaniesActivate: 'Company ko activate karta hai.',
  platformCompaniesDeactivate: 'Company ko deactivate karta hai.',
};
const PATH_HI = {
  'get /auth/email/verify/{id}/{hash}': 'Email verification ka signed URL — user email ke link se yahan aata hai.',
  'get /auth/2fa/recovery-codes': 'Naye recovery codes banata hai; purane invalid ho jate hain. Recent password confirmation zaroori hai.',
};
const cap1 = (t) => t.replace(/^([a-z])/, (c) => c.toUpperCase());
function explain(op, method, p, summary) {
  const hi = OVERRIDE_HI[op.operationId] || PATH_HI[`${method} ${p}`];
  return hi || cap1(explainGeneric(op, method, p, summary));
}
function explainGeneric(op, method, p, summary) {
  const id = op.operationId;
  const paged = isPaginated(op);
  const file = fileContentType(op);
  const m = /^(List|Create|Get|Update|Delete) (.+?)(?: \(platform\))?$/.exec(summary);
  const act = /^(.+?) – (.+?)(?: \(platform\))?$/.exec(summary);
  const res = m ? m[2] : act ? act[2] : resourceFromPath(p, /Index$/.test(id));
  if (file) return `${act ? act[1] + ' — ' : ''}${res} ki file download karta hai (<code>${esc(file)}</code>, <code>Content-Disposition: attachment</code>).`;
  if (/Index$/.test(id) || (m && m[1] === 'List')) return `${m ? m[2] : resourceFromPath(p, true)} ki list deta hai${paged ? ' — results paginated hote hain (<code>page</code>, <code>per_page</code>).' : '.'}`;
  if (/Store$/.test(id) || (m && m[1] === 'Create')) {
    const has201 = !!op.responses?.['201'];
    return `Naya ${m ? m[2] : resourceFromPath(p)} banata hai${has201 ? '; kamyabi par <code>201</code> aur naya record <code>data</code> mein wapas aata hai.' : '.'}`;
  }
  if (/Show$/.test(id) || (m && m[1] === 'Get')) return `Ek ${m ? m[2] : resourceFromPath(p)} ki poori tafseel deta hai.`;
  if (/UpdatePatch$/.test(id)) return `${res} update karta hai — ye PATCH variant hai, handler wahi hai jo PUT ka hai.`;
  if (/Update$/.test(id) || (m && m[1] === 'Update')) return `Maujooda ${res} ko update karta hai.`;
  if (/Destroy$/.test(id) || (m && m[1] === 'Delete')) return `${res} delete karta hai${op.responses?.['204'] ? ' — kamyabi par <code>204</code> (khaali body).' : '.'}`;
  if (/\/reports\//.test(p) && method === 'get') return `${act ? act[1] : summary} report ka data JSON mein deta hai.`;
  if (act) {
    const verb = act[1].toLowerCase();
    if (method === 'post' && VERB_HI[verb]) return VERB_HI[verb](act[2]);
    if (method === 'get') return `${act[2]} se mutalliq <code>${esc(act[1].toLowerCase())}</code> data deta hai.`;
    return `${act[2]} par <code>${esc(act[1].toLowerCase())}</code> action perform karta hai.`;
  }
  if (method === 'get') return 'Read-only endpoint — upar diye gaye summary ke mutabiq data lautata hai.';
  if (method === 'delete') return `${resourceFromPath(p)} delete/remove karta hai — tafseel upar summary mein.`;
  return 'Upar summary mein bataya gaya action perform karta hai (state change).';
}

function parseDescription(desc = '') {
  const paras = String(desc).split(/\n\n+/);
  const keep = [];
  let route = [], policy = [];
  for (const para of paras) {
    const t = para.trim();
    if (!t) continue;
    if (/^\*\*Requires permission:\*\*/.test(t)) { route = [...t.matchAll(/`([^`]+)`/g)].map((x) => x[1]); continue; }
    if (/^\*\*Policy requires:\*\*/.test(t)) { policy = [...t.split('(')[0].matchAll(/`([^`]+)`/g)].map((x) => x[1]); continue; }
    if (/^\*\*Policy checks:\*\*|^Handler:|^Additional rate limit/.test(t)) continue;
    keep.push(t.replace(/\s*\*\/\s*$/, ''));
  }
  return { notes: keep, route, policy };
}

// Collect operations grouped by tag
const ops = [];
for (const [p, item] of Object.entries(spec.paths)) {
  for (const method of METHODS) {
    const op = item[method];
    if (!op) continue;
    const tag = (op.tags && op.tags[0]) || 'Other';
    const summary = cleanSummary(op.summary, op, method, p);
    ops.push({ p, method, op, tag, summary });
  }
}
const byTag = new Map(TAG_ORDER.map((t) => [t, []]));
for (const o of ops) { if (!byTag.has(o.tag)) byTag.set(o.tag, []); byTag.get(o.tag).push(o); }

// ───────────────────────── renderers ─────────────────────────
const badge = (m) => `<span class="m m-${m}">${m.toUpperCase()}</span>`;
const pathHtml = (p) => esc(p).replace(/\{([^}]+)\}/g, '<span class="pp">{$1}</span>');
const STD_PARAMS = { Page: 'page', PerPage: 'per_page', Search: 'search', Sort: 'sort' };

function authLabel(op) {
  const sec = op.security || spec.security;
  const keys = new Set(sec.flatMap((x) => Object.keys(x)));
  if (!keys.has('cookieAuth')) return keys.has('xsrfToken') ? 'Public (login nahi chahiye) + <code>X-XSRF-TOKEN</code>' : 'Public (login nahi chahiye)';
  return keys.has('xsrfToken') ? 'Session cookie + <code>X-XSRF-TOKEN</code>' : 'Session cookie';
}

function renderParams(op) {
  const std = [];
  let tenant = null;
  const rows = [];
  for (const prm of op.parameters || []) {
    if (prm.$ref) {
      const n = refName(prm.$ref);
      if (n === 'CompanyHeader') { tenant = 'required'; continue; }
      if (n === 'CompanyHeaderOptional') { tenant = 'optional'; continue; }
      if (STD_PARAMS[n]) { std.push(STD_PARAMS[n]); continue; }
      const d = P[n];
      rows.push(d);
      continue;
    }
    rows.push(prm);
  }
  let html = '';
  if (rows.length) {
    html += `<table class="t"><thead><tr><th>Parameter</th><th>In</th><th>Type</th><th>Req.</th><th>Description / constraints</th></tr></thead><tbody>`;
    for (const r of rows) {
      const s = r.schema || {};
      let desc = r.description || '';
      if (/^ID of the /.test(desc)) desc = desc.split(' (')[0] + '.';
      if (/^Exact-match filter on/.test(desc)) desc = 'Exact-match filter (comma-separated = koi bhi value).';
      let cons = constraints(s, { withDesc: false });
      if (s.enum && s.enum.length > 12) cons = 'values: ' + s.enum.filter((v) => !String(v).startsWith('-')).map((v) => `<code>${esc(v)}</code>`).join(', ') + ' (prefix <code>-</code> = descending)' + (s.default ? ` · default <code>${esc(s.default)}</code>` : '');
      else if (r.name === 'sort' && s.enum) cons = 'values: ' + s.enum.map((v) => `<code>${esc(v)}</code>`).join(', ') + (s.default ? ` · default <code>${esc(s.default)}</code>` : '');
      html += `<tr><td><code>${esc(r.name)}</code></td><td>${esc(r.in)}</td><td>${typeLabel(s)}</td><td>${r.required ? '<b class="req">yes</b>' : 'no'}</td><td>${[md(desc), cons].filter(Boolean).join(' · ')}</td></tr>`;
    }
    html += `</tbody></table>`;
  }
  return { tenant, std, html };
}

function renderBody(op) {
  const rb = op.requestBody;
  if (!rb) return '';
  const [ct, media] = Object.entries(rb.content || {})[0] || [];
  if (!media) return '';
  const rows = flatten(media.schema || {});
  let html = `<div class="sec">Request body <span class="ct">${esc(ct)}</span>${rb.required ? '' : ' <span class="muted">(optional)</span>'}</div>`;
  if (!rows.length) {
    html += `<p class="muted small">Body ka koi fixed schema nahi (${typeLabel(media.schema || {})}).</p>`;
    return html;
  }
  html += `<table class="t"><thead><tr><th>Field</th><th>Type</th><th>Req.</th><th>Constraints / notes</th></tr></thead><tbody>`;
  for (const r of rows) html += `<tr><td><code>${esc(r.name)}</code></td><td>${r.type}</td><td>${r.required ? '<b class="req">yes</b>' : 'no'}</td><td>${r.notes}</td></tr>`;
  html += `</tbody></table>`;
  return html;
}

function isCreate(o) {
  return o.method === 'post' && (/Store$/.test(o.op.operationId) || !!o.op.responses?.['201']) &&
    o.op.requestBody && Object.keys(o.op.requestBody.content || {})[0] === 'application/json';
}
function compactJson(v, ind = '') {
  const one = JSON.stringify(v);
  if (one.length + ind.length <= 88 || v === null || typeof v !== 'object') return one;
  const nx = ind + '  ';
  const NL = '\n';
  if (Array.isArray(v)) return '[' + NL + v.map((x) => nx + compactJson(x, nx)).join(',' + NL) + NL + ind + ']';
  return '{' + NL + Object.entries(v).map(([k, x]) => nx + JSON.stringify(k) + ': ' + compactJson(x, nx)).join(',' + NL) + NL + ind + '}';
}
function renderExample(o) {
  if (!isCreate(o)) return '';
  const schema = mergeAllOf(deref(o.op.requestBody.content['application/json'].schema));
  if (!schema.properties) return '';
  const ex = sampleObject(schema, 0);
  return `<div class="sec">Example request</div><pre class="code">${esc(compactJson(ex))}</pre>`;
}

function fieldChips(schema, cap = 25) {
  const s = mergeAllOf(deref(schema));
  const entries = Object.entries(s.properties || {});
  if (!entries.length) return '';
  const shown = entries.slice(0, cap).map(([k, v]) => `<span class="f"><code>${esc(k)}</code> <i>${typeLabel(v)}</i></span>`);
  const more = entries.length > cap ? ` <span class="muted">…aur ${entries.length - cap} fields</span>` : '';
  return `<div class="fields">${shown.join('')}${more}</div>`;
}

function describeSuccess(code, r) {
  const ct = Object.keys(r.content || {});
  if (!ct.length) return `<li><b>${code}</b> — ${md(r.description || '')}${code === '204' ? ' (khaali body)' : ''}</li>`;
  const json = r.content['application/json'];
  if (!json) return `<li><b>${code}</b> — File (${ct.map((c) => `<code>${esc(c)}</code>`).join(', ')}). ${md(r.description || '')}</li>`;
  const sch = json.schema || {};
  const inferred = r['x-schema-source'] === 'inferred-from-code' ? ' <span class="tag-inf">shape inferred from code</span>' : '';
  let line = `<li><b>${code}</b> — `;
  let detail = '';
  const top = mergeAllOf(deref(sch));
  const variants = sch.oneOf || sch.anyOf;
  if (variants) {
    line += `Response in shapes mein se ek: ` + variants.map((v) => '{' + Object.keys(mergeAllOf(deref(v)).properties || {}).map((k) => `<code>${esc(k)}</code>`).join(', ') + '}').join(' ya ');
  } else if (top.properties && top.properties.data) {
    const d = top.properties.data;
    const meta = top.properties.meta;
    const dd = deref(d);
    if (d.$ref || (d.items && d.items.$ref)) {
      const ref = d.$ref || d.items.$ref;
      line += `<code>data</code>: ${d.items ? 'array of ' : ''}${typeLabel({ $ref: ref })}`;
      detail = fieldChips({ $ref: ref });
    } else if (types(d).includes('array') || d.items) {
      line += `<code>data</code>: array of ${d.items && Object.keys(d.items).length ? typeLabel(d.items) : 'objects'}`;
      if (d.items && d.items.properties) detail = fieldChips(d.items);
    } else if (dd.properties) {
      line += `<code>data</code>: object`;
      detail = fieldChips(dd);
    } else {
      line += `<code>data</code>: ${typeLabel(d)}`;
    }
    if (meta) line += meta.$ref?.endsWith('PaginationMeta') ? ' + <code>meta.pagination</code>' : ` + <code>meta</code> {${Object.keys(meta.properties || {}).map((k) => `<code>${esc(k)}</code>`).join(', ')}}`;
  } else if (top.properties) {
    line += `Object:`;
    detail = fieldChips(top);
  } else {
    line += md(r.description || 'OK');
  }
  return line + inferred + detail + '</li>';
}

function renderResponses(op) {
  const succ = successInfo(op);
  const errs = Object.keys(op.responses || {}).filter((c) => !/^2/.test(c));
  let html = `<div class="sec">Responses</div><ul class="resp">`;
  for (const { code, r } of succ) html += describeSuccess(code, r);
  if (errs.length) {
    const lbl = errs.map((c) => {
      const rr = op.responses[c];
      const n = rr.$ref ? refName(rr.$ref) : null;
      const ex = n ? R[n].content?.['application/json']?.example?.code : null;
      return `<code>${c}</code>${ex ? `<span class="muted"> ${esc(ex)}</span>` : (rr.description ? `<span class="muted"> ${esc(rr.description.replace(/\.$/, ''))}</span>` : '')}`;
    });
    html += `<li class="errs"><b>Errors:</b> ${lbl.join(' · ')}</li>`;
  }
  return html + '</ul>';
}

let cardCount = 0;
function renderOp(o) {
  cardCount++;
  const { op, method, p, summary } = o;
  const d = parseDescription(op.description);
  const perms = op['x-permissions'] || [];
  const { tenant, std, html: paramHtml } = renderParams(op);
  const thr = (op['x-throttle'] || []).map((t) => `<code>${esc(t)}</code>`).join(' ');
  let permHtml;
  if (!perms.length) permHtml = '<span class="muted">Koi permission slug nahi (sirf authentication/ownership)</span>';
  else {
    const parts = [];
    if (d.route.length) parts.push(`Route: ${d.route.map((x) => `<span class="perm">${esc(x)}</span>`).join(' ya ')}`);
    if (d.policy.length) parts.push(`Policy: ${d.policy.map((x) => `<span class="perm">${esc(x)}</span>`).join(' / ')}`);
    if (!parts.length) parts.push(perms.map((x) => `<span class="perm">${esc(x)}</span>`).join(' '));
    permHtml = parts.join(' &nbsp;·&nbsp; ');
  }
  if (/UpdatePatch$/.test(op.operationId)) {
    const putId = op.operationId.replace(/Patch$/, '');
    if (ops.some((x) => x.op.operationId === putId && x.p === p)) {
      return `<div class="op keep" id="${opAnchor(op)}">
<div class="op-head">${badge(method)}<span class="path"><span class="base">/api/v1</span>${pathHtml(p)}</span></div>
<div class="op-title">${md(summary)} <span class="opid">${esc(op.operationId)}</span></div>
<p class="hi">Ye PUT wale update ka PATCH variant hai. Parameters, request body, permissions aur responses bilkul <a href="#op-${putId}"><code>PUT ${esc(p)}</code></a> jaise hain (route <code>PUT|PATCH</code> ek hi handler share karta hai).</p>
<table class="kv"><tbody><tr><th>Permission</th><td>${perms.map((x) => `<span class="perm">${esc(x)}</span>`).join(' ') || '<span class="muted">—</span>'}</td></tr></tbody></table>
</div>`;
    }
  }
  const pwConfirm = op.responses?.['423'] ? '<div class="warn">Sensitive action: pichhle 15 minute mein <code>POST /auth/confirm-password</code> zaroori hai, warna <code>423</code>.</div>' : '';
  const notes = d.notes.length ? `<div class="note">${d.notes.map(md).join('<br>')}</div>` : '';
  const showSummaryAsIs = summary;
  const bodyHtml = renderBody(op), exHtml = renderExample(o), respHtml = renderResponses(op);
  const weight = (paramHtml + bodyHtml).split('<tr>').length + exHtml.split(/\n/).length * 0.6;
  return `<div class="op${weight < 22 ? ' keep' : ''}" id="${opAnchor(op)}">
<div class="op-head">${badge(method)}<span class="path"><span class="base">/api/v1</span>${pathHtml(p)}</span></div>
<div class="op-title">${md(showSummaryAsIs)} <span class="opid">${esc(op.operationId)}</span></div>
<p class="hi">${explain(op, method, p, summary)}</p>
${notes}
<table class="kv"><tbody>
<tr><th>Auth</th><td>${authLabel(op)}${tenant ? ` · <code>X-Company-Id</code> ${tenant === 'required' ? '<b class="req">required</b>' : 'optional'}` : ''}</td></tr>
<tr><th>Permission</th><td>${permHtml}${op['x-policy'] ? ` <span class="muted small">(${op['x-policy'].map(esc).join('; ')})</span>` : ''}</td></tr>
<tr><th>Throttle</th><td>${thr}</td></tr>
${std.length ? `<tr><th>List params</th><td>${std.map((x) => `<code>${x}</code>`).join(', ')} <span class="muted">(standard — §Pagination dekhein)</span></td></tr>` : ''}
</tbody></table>
${pwConfirm}
${paramHtml ? `<div class="sec">Parameters</div>${paramHtml}` : ''}
${bodyHtml}
${exHtml}
${respHtml}
</div>`;
}

function renderTag(tag, list) {
  const tagDef = spec.tags.find((t) => t.name === tag) || {};
  let h = `<section class="chapter tag" id="tag-${slug(tag)}"><h1><span class="kicker">Module</span>${esc(tag)}</h1>
<p class="lead">${TAG_INTRO[tag] || md(tagDef.description || '')}</p>
<p class="muted small">Spec: ${md(tagDef.description || '')} — ${list.length} operations.</p>
<table class="t ov"><thead><tr><th>Method</th><th>Path</th><th>Summary</th><th>Permission</th></tr></thead><tbody>`;
  for (const o of list) {
    h += `<tr><td>${badge(o.method)}</td><td><a class="mono" href="#${opAnchor(o.op)}">${pathHtml(o.p)}</a></td><td>${md(o.summary)}</td><td>${(o.op['x-permissions'] || []).map((x) => `<span class="perm">${esc(x)}</span>`).join(' ') || '<span class="muted">—</span>'}</td></tr>`;
  }
  h += `</tbody></table><h2 class="ops-h">Endpoints</h2>`;
  for (const o of list) h += renderOp(o);
  return h + '</section>';
}

// ───────────────────────── Shuruaat chapters ─────────────────────────
function errorTable() {
  const rows = [];
  const descTable = spec.info.description.split('\n').filter((l) => /^\| \d{3} \|/.test(l));
  const hi = {
    'auth.unauthenticated': 'Session nahi hai ya expire ho gaya — dobara login karein.',
    'auth.forbidden': 'Permission nahi hai ya policy ne mana kar diya.',
    'tenant.forbidden': '<code>X-Company-Id</code> missing hai ya us company mein active membership nahi.',
    'not_found': 'Route/record nahi mila — doosri company ka record bhi 404 deta hai; platform routes non-platform users ke liye bhi 404.',
    'conflict': 'Record ki maujooda state is action ki ijazat nahi deti (jaise non-pending record approve karna).',
    'finance.period_closed': 'Ledger refusal — period closed, period maujood nahi, ya posted entry immutable hai.',
    'inventory.insufficient_stock': 'Outbound movement stock se zyada; <code>details</code> mein figures.',
    'http.410': 'Export file expire ho chuki — report dobara chalayein.',
    'auth.csrf': 'CSRF token missing/galat ya session expire.',
    'validation.failed': 'Validation errors <code>errors</code> object mein (field → messages).',
    'tenant.mismatch': 'Body mein diya gaya koi id doosri company ka hai.',
    'finance.unbalanced': 'Journal entry ke debits aur credits barabar nahi.',
    'http.423': 'Password confirmation zaroori (<code>POST /auth/confirm-password</code>).',
    'throttled': 'Rate limit hit — <code>Retry-After</code> header dekhein.',
    'server.error': 'Ghair-mutawaqqa error (APP_DEBUG ke baghair stack trace nahi).',
  };
  for (const l of descTable) {
    const cells = l.split('|').map((c) => c.trim()).filter(Boolean);
    const [status, code, meaning] = cells;
    const firstCode = (code.match(/`([^`]+)`/) || [])[1];
    rows.push(`<tr><td><b>${status}</b></td><td>${md(code)}</td><td>${md(meaning)}</td><td>${hi[firstCode] || ''}</td></tr>`);
  }
  return `<table class="t"><thead><tr><th>HTTP</th><th>code</th><th>Meaning (spec)</th><th>Matlab</th></tr></thead><tbody>${rows.join('')}</tbody></table>`;
}

function responsesTable() {
  return `<table class="t"><thead><tr><th>Component</th><th>Description</th><th>Example body</th></tr></thead><tbody>${Object.entries(R).map(([k, r]) => {
    const ex = r.content?.['application/json']?.example;
    return `<tr><td><code>${k}</code></td><td>${md(r.description)}${r.headers ? ` <span class="muted">Headers: ${Object.keys(r.headers).map((h) => `<code>${h}</code>`).join(', ')}</span>` : ''}</td><td><code class="wrap">${esc(JSON.stringify(ex))}</code></td></tr>`;
  }).join('')}</tbody></table>`;
}

const totalOps = ops.length;
const pathsCount = Object.keys(spec.paths).length;

const GETTING_STARTED = `
<section class="chapter" id="ch-intro"><h1><span class="kicker">Shuruaat</span>Ta'aruf (Introduction)</h1>
<p>Ye document Enterprise ERP ke REST API ka mukammal reference hai. Backend Laravel 13 par bana hai aur authentication Laravel Sanctum ke <b>SPA (stateful cookie)</b> mode se hoti hai. Ye reference seedha code se generate hone wali OpenAPI 3.1 spec (<code>docs/api/openapi.yaml</code>, version <code>${esc(VERSION)}</code>) se banaya gaya hai: <b>${pathsCount} paths</b> par <b>${totalOps} operations</b>, aur <b>${Object.keys(S).length} schemas</b>.</p>
<table class="t"><tbody>
<tr><th>Base URL (local)</th><td><code>${BASE}</code></td></tr>
<tr><th>Base URL (production example)</th><td><code>${PROD}</code></td></tr>
<tr><th>CSRF cookie route</th><td><code>GET /sanctum/csrf-cookie</code> — ye <code>/api/v1</code> se bahar web route hai (local: <code>http://localhost:8000/sanctum/csrf-cookie</code>)</td></tr>
<tr><th>Format</th><td>JSON (<code>Accept: application/json</code>); uploads <code>multipart/form-data</code></td></tr>
<tr><th>Auth</th><td>Session cookie <code>enterprise-erp-session</code> + <code>X-XSRF-TOKEN</code> header (mutating requests par)</td></tr>
<tr><th>Tenant</th><td><code>X-Company-Id</code> header (module routes par zaroori)</td></tr>
</tbody></table>
<h3>Is document ko kaise parhein</h3>
<ul>
<li><b>Shuruaat</b> chapters mein woh sab conventions hain jo har endpoint par lagu hote hain (auth, tenancy, permissions, envelope, errors, pagination, money/dates, rate limits, files, versioning). Ye cheezein har endpoint par dobara nahi likhi gayin.</li>
<li><b>Endpoint reference</b> har module (tag) ke liye ek naye page se shuru hota hai: pehle module ka ta'aruf aur overview table, phir har operation ka card.</li>
<li>Har card mein: method badge, poora path, summary, Hinglish wazahat, auth/permission/throttle, parameters, request body fields (nested arrays <code>lines[].field</code> ki shakal mein), create endpoints ke liye example JSON, aur responses.</li>
<li><b>Appendix A</b> mein saare object schemas, <b>Appendix B</b> mein enums, aur <b>Appendix C</b> mein permission slug → endpoints index hai. Blue links clickable hain.</li>
<li>Kuch responses par <span class="tag-inf">shape inferred from code</span> likha hai: ye ad-hoc JSON (reports, dashboards, summaries) hai jiski shape controller code se infer ki gayi hai, is liye kam precise ho sakti hai.</li>
</ul>
<p class="muted small">Method colours: ${badge('get')} read · ${badge('post')} create/action · ${badge('put')} ${badge('patch')} update · ${badge('delete')} delete.</p>
</section>

<section class="chapter" id="ch-auth"><h1><span class="kicker">Shuruaat</span>Authentication flow</h1>
<p>API <b>Sanctum SPA cookie authentication</b> use karti hai — koi bearer token ya API key nahi hoti, aur kuch bhi secret <code>localStorage</code> mein nahi rakha jata. Browser ko <code>HttpOnly</code>, <code>SameSite=Lax</code> (production mein <code>Secure</code>) session cookie milti hai. Cookie ka naam <code>enterprise-erp-session</code> hai (<code>SESSION_COOKIE</code> se set hota hai; Laravel ka default <code>laravel_session</code> hota). CORS sirf configured frontend origin (<code>FRONTEND_URL</code>) ke liye credentials allow karta hai, aur <code>SANCTUM_STATEFUL_DOMAINS</code> / <code>SESSION_DOMAIN</code> frontend host se match hone chahiye.</p>

<h3>Step 1 — CSRF cookie lein</h3>
<p><code>GET /sanctum/csrf-cookie</code> call karein (ye <code>/api/v1</code> ke bahar hai). Server <code>XSRF-TOKEN</code> cookie set karta hai. Is cookie ki <b>URL-decoded</b> value har mutating request (POST/PUT/PATCH/DELETE) mein <code>X-XSRF-TOKEN</code> header ke taur par bhejni hoti hai. Axios ye kaam khud karta hai; curl mein haath se karna padta hai.</p>
<pre class="code"># cookie jar file: cookies.txt   (Origin/Referer frontend ka hona chahiye — SANCTUM_STATEFUL_DOMAINS)
curl -s -c cookies.txt -b cookies.txt \\
  -H "Accept: application/json" -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" \\
  http://localhost:8000/sanctum/csrf-cookie

# XSRF-TOKEN cookie ki value nikaal kar URL-decode karein (%3D → =)
XSRF=$(awk '$6=="XSRF-TOKEN"{print $7}' cookies.txt | sed 's/%3D/=/g')</pre>

<h3>Step 2 — Login</h3>
<p><code>POST /auth/login</code> par <code>email</code>, <code>password</code> (aur optional <code>remember</code>) bhejein, <code>X-XSRF-TOKEN</code> header ke saath. Is route par alag <code>login</code> rate limit hai: 5/minute per email+IP aur 30/minute per IP. Galat email aur galat password dono par ek jaisa message milta hai (account ka wujood zahir nahi hota).</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt \\
  -H "Accept: application/json" -H "Content-Type: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" \\
  -H "X-XSRF-TOKEN: $XSRF" \\
  -d '{"email":"ali.raza@example.com","password":"S3cure-Passw0rd","remember":false}' \\
  ${BASE}/auth/login</pre>
<p>Response do mein se ek shape ka hota hai:</p>
<ul>
<li><code>{"two_factor": false, "session": {...}}</code> — login mukammal; session cookie jar mein aa gayi.</li>
<li><code>{"two_factor": true}</code> — user ka 2FA on hai. User <b>abhi authenticated nahi</b>; session mein ek short-lived challenge rakha gaya hai. Step 3 par jayein.</li>
</ul>
<p class="muted small">Tip: har response naya <code>XSRF-TOKEN</code> cookie bhi bhej sakta hai — curl script mein mutating request se pehle <code>$XSRF</code> ko cookie jar se dobara parh lena mehfooz hai.</p>

<h3>Step 3 — 2FA challenge (sirf agar <code>two_factor: true</code>)</h3>
<p><code>POST /auth/2fa/challenge</code> par authenticator app ka 6-digit TOTP <code>code</code> <b>ya</b> ek <code>recovery_code</code> bhejein (dono mein se ek zaroori). Recovery codes hashed aur single-use hain. Rate limit: <code>two-factor</code> 5/minute per pending 2FA session.</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -H "Accept: application/json" -H "Content-Type: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" -H "X-XSRF-TOKEN: $XSRF" \\
  -d '{"code":"123456"}' ${BASE}/auth/2fa/challenge</pre>

<h3>Step 4 — Session bootstrap</h3>
<p><code>GET /auth/session</code> logged-in user, uski company memberships (<code>companies</code>), <code>current_company</code>, active company ke liye <code>permissions</code> (slugs) aur branches deta hai. Frontend isi se menu aur buttons decide karta hai — lekin asal enforcement hamesha server par hota hai.</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -H "Accept: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" \\
  ${BASE}/auth/session</pre>

<h3>Step 5 — Aage ki requests</h3>
<ul>
<li>Har request session cookie bhejti hai (browser mein <code>withCredentials: true</code>).</li>
<li>Har <b>mutating</b> request (POST/PUT/PATCH/DELETE) <code>X-XSRF-TOKEN</code> bhi bhejti hai. Token missing/stale ho to <b>419</b> <code>auth.csrf</code>.</li>
<li>Module routes par <code>X-Company-Id</code> header (agla chapter).</li>
</ul>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -H "Accept: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" \\
  -H "X-Company-Id: 1" "${BASE}/invoices?page=1&amp;per_page=25&amp;sort=-invoice_date"</pre>

<h3>Step 6 — Logout</h3>
<p><code>POST /auth/logout</code> session khatam karta hai aur <code>204</code> deta hai. Password change (<code>POST /auth/password</code>) ya reset (<code>POST /auth/reset-password</code>) par user ke <b>baqi saare sessions</b> bhi invalidate ho jate hain. Inactive (deactivated) user agli request par khud logout ho jata hai.</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -X POST -H "Accept: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" -H "X-XSRF-TOKEN: $XSRF" \\
  ${BASE}/auth/logout</pre>

<h3>Password confirmation (re-authentication)</h3>
<p>Kuch sensitive operations <code>password.confirm</code> middleware ke peeche hain (jaise 2FA recovery codes). In ke liye pichhle <b>15 minute</b> mein <code>POST /auth/confirm-password</code> (<code>{"password": "..."}</code>) kiya hona chahiye — response mein <code>confirmed_until</code> aata hai. Warna <b>423</b> <code>http.423</code> milta hai.</p>
<h3>2FA on/off karna</h3>
<p><code>POST /auth/2fa/enable</code> ek pending secret generate karta hai; 2FA tabhi active hota hai jab <code>POST /auth/2fa/confirm</code> se TOTP code confirm ho jaye. <code>POST /auth/2fa/disable</code> se band hota hai, aur <code>GET /auth/2fa/recovery-codes</code> naye recovery codes banata hai (purane invalid ho jate hain).</p>
</section>

<section class="chapter" id="ch-tenancy"><h1><span class="kicker">Shuruaat</span>Tenancy: <code>X-Company-Id</code></h1>
<p>System multi-company hai — <code>companies</code> tenant root hai aur har tenant-owned table mein <code>company_id</code> hota hai. <code>/health*</code>, <code>/auth/*</code> aur <code>/platform/*</code> ke ilawa <b>saare module routes</b> ek company context mein chalte hain. Active company ka id <code>X-Company-Id</code> header mein bhejein.</p>
<ol>
<li>Server header parhta hai (file downloads jo naye tab mein khulte hain, unke liye <code>company_id</code> query parameter bhi chalta hai).</li>
<li>Verify karta hai ke logged-in user ki us company mein <b>active membership</b> hai.</li>
<li>Agar nahi, to <b>403</b> <code>tenant.forbidden</code>.</li>
</ol>
<ul>
<li><b>Doosri company ka record id</b> (URL mein) hamesha <b>404</b> deta hai — record ka wujood zahir nahi kiya jata.</li>
<li><b>Request body mein</b> doosri company ka id (jaise <code>customer_id</code>, <code>warehouse_id</code>) dena <b>422</b> <code>tenant.mismatch</code> deta hai.</li>
<li><code>company_id</code> client se kabhi trust nahi hota — create par server khud context se set karta hai.</li>
<li>Branch restriction wale users (<code>branch_ids</code> membership par) sirf apni branches ke records dekhte hain.</li>
</ul>
<h3>Company switch karna</h3>
<p><code>POST /auth/switch-company</code> body <code>{"company_id": 2}</code> ke saath. Server membership verify karta hai aur switch audit log mein jata hai; response mein naya <code>Session</code> aata hai. Is ke baad client har request par naya <code>X-Company-Id</code> bhejta hai.</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -H "Accept: application/json" -H "Content-Type: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" -H "X-XSRF-TOKEN: $XSRF" \\
  -d '{"company_id":2}' ${BASE}/auth/switch-company</pre>
</section>

<section class="chapter" id="ch-perms"><h1><span class="kicker">Shuruaat</span>Permissions model</h1>
<p>Authorization do layers mein hota hai:</p>
<ul>
<li><b>Route middleware</b> <code>permission:&lt;slug&gt;</code> — coarse RBAC. Agar route par kai slugs listed hain to <b>koi ek</b> kaafi hai.</li>
<li><b>Policies</b> — fine-grained check (har controller action <code>authorize()</code> call karta hai). Kuch abilities record ke owner / self-service ko bhi allow karti hain.</li>
</ul>
<p>Permission slugs <code>module.action</code> format mein hain (jaise <code>invoices.approve</code>), roles ke zariye users ko milte hain, aur <b>active company</b> ke hisaab se resolve hote hain. Current user ke slugs <code>GET /auth/session</code> → <code>permissions</code> mein hain.</p>
<h3><code>x-permissions</code> ka matlab</h3>
<p>Har operation par spec ka <code>x-permissions</code> route middleware aur policy abilities dono ke slugs list karta hai. Is reference mein card par ye alag dikhaye gaye hain: <b>Route:</b> (middleware slugs, "ya" = koi ek) aur <b>Policy:</b> (policy jo slugs check karti hai), saath mein policy ability (jaise <code>viewAny on Invoice</code>). Agar koi slug nahi, to sirf authentication (ya ownership) kaafi hai. Poora index Appendix C mein hai.</p>
<h3>403 vs 404</h3>
<table class="t"><thead><tr><th>Surat-e-haal</th><th>Response</th></tr></thead><tbody>
<tr><td>Permission nahi / policy ne deny kiya</td><td><b>403</b> <code>auth.forbidden</code></td></tr>
<tr><td><code>X-Company-Id</code> missing ya membership nahi</td><td><b>403</b> <code>tenant.forbidden</code></td></tr>
<tr><td>Record doosri company ka hai (URL id)</td><td><b>404</b> <code>not_found</code> (existence disclose nahi hoti)</td></tr>
<tr><td>Body mein doosri company ka id</td><td><b>422</b> <code>tenant.mismatch</code></td></tr>
<tr><td>Non-platform user platform route par</td><td><b>404</b> <code>not_found</code></td></tr>
</tbody></table>
<p>Platform roles (<code>platform.owner</code>, <code>platform.admin</code>) ki <code>company_id</code> NULL hoti hai aur sirf yehi <code>/api/v1/platform/*</code> tak pahunchte hain. Sensitive actions (payroll processing, invoice approval, user management, report export, inventory adjustment, journal posting) audit log mein darj hote hain. UI mein button chhupana kabhi akela check nahi hota.</p>
</section>

<section class="chapter" id="ch-envelope"><h1><span class="kicker">Shuruaat</span>Request/response envelope &amp; errors</h1>
<h3>Success</h3>
<ul>
<li>Single resource: <code>{ "data": { ... } }</code></li>
<li>List: <code>{ "data": [ ... ], "meta": { "pagination": { "page", "per_page", "total", "last_page" } } }</code></li>
<li>Create <b>201</b> deta hai (naya record <code>data</code> mein), delete <b>204</b> khaali body ke saath.</li>
</ul>
<pre class="code">{
  "data": [ { "id": 41, "number": "INV-000041", "total": "1250.0000", "status": "approved" } ],
  "meta": { "pagination": { "page": 1, "per_page": 25, "total": 1, "last_page": 1 } }
}</pre>
<p class="muted small">Kuch endpoints jo raw paginated collection lautate hain unme Laravel paginator ki <code>links</code>/extra meta keys bhi aa sakti hain; clients ko sirf <code>meta.pagination</code> par bharosa karna chahiye.</p>
<h3>Errors</h3>
<p>Har error ek hi envelope use karta hai: <code>{ "message": string, "code": string, "errors"?: {field: [messages]}, "details"?: object }</code>. <code>message</code> user ko dikhane ke liye safe hai; <code>code</code> stable machine code hai — client logic isi par banayein. Validation errors ke field paths dot notation mein hote hain (jaise <code>lines.0.quantity</code>). <code>debug</code> sirf <code>APP_DEBUG=true</code> par 500 responses mein aata hai, production mein kabhi nahi.</p>
<pre class="code">HTTP/1.1 422 Unprocessable Content
{ "message": "Please correct the highlighted fields.", "code": "validation.failed",
  "errors": { "name": ["The name field is required."] } }</pre>
<h3>Status codes &amp; error codes</h3>
${errorTable()}
<h3>Shared response components</h3>
<p>Spec ke <code>components.responses</code> — endpoint cards par error list inhi ki taraf ishara karti hai:</p>
${responsesTable()}
</section>

<section class="chapter" id="ch-lists"><h1><span class="kicker">Shuruaat</span>Pagination, search, sort, filter</h1>
<p>List endpoints (jin ke card par "List params" likha hai) ye standard query parameters lete hain. Ye parameters har card par dobara document nahi kiye gaye — sirf naam diye gaye hain.</p>
<table class="t"><thead><tr><th>Parameter</th><th>Type</th><th>Tafseel</th></tr></thead><tbody>
<tr><td><code>page</code></td><td>integer ≥ 1</td><td>1-based page number (default 1).</td></tr>
<tr><td><code>per_page</code></td><td>integer 1–100</td><td>Page size, 1–100 mein clamp hota hai; default <b>25</b> (<code>config/erp.php</code> → <code>pagination.default</code>).</td></tr>
<tr><td><code>search</code></td><td>string ≤ 200</td><td>Case-insensitive substring match; har endpoint ke allow-listed columns card ke note mein ("<code>search</code> matches …") diye gaye hain.</td></tr>
<tr><td><code>sort</code></td><td>string</td><td>Endpoint ki allow-list se ek column; <code>-</code> prefix = descending (jaise <code>sort=-created_at</code>). Unknown column default par wapas chala jata hai; primary key tie-breaker ke taur par hamesha lagti hai.</td></tr>
<tr><td><code>filter[&lt;column&gt;]</code></td><td>string</td><td>Exact match. Kai values: <code>filter[status]=draft,approved</code> ya <code>filter[status][]=draft&amp;filter[status][]=approved</code> (koi bhi match). Booleans <code>true</code>/<code>false</code>.</td></tr>
<tr><td><code>from</code> / <code>to</code></td><td>date <code>YYYY-MM-DD</code></td><td>List ke main date column par inclusive range (UTC day start/end). Kaunsa column — card par likha hai.</td></tr>
<tr><td><code>branch_id</code></td><td>integer</td><td>Jahan supported ho, branch ke hisaab se filter.</td></tr>
</tbody></table>
<pre class="code">GET ${BASE}/invoices?page=2&amp;per_page=50&amp;search=INV-00&amp;sort=-due_date&amp;filter[status]=approved,partially_paid&amp;from=2026-01-01&amp;to=2026-06-30
X-Company-Id: 1</pre>
<p>Selectors ke liye kai modules mein lightweight, bina pagination wale endpoints bhi hain (jaise <code>/branches/all</code>, <code>/customers/all</code>, <code>/suppliers/all</code>, <code>/warehouses/all</code>).</p>
</section>

<section class="chapter" id="ch-money"><h1><span class="kicker">Shuruaat</span>Money, numbers &amp; dates</h1>
<ul>
<li><b>Money</b> hamesha <code>DECIMAL(18,4)</code> <b>string</b> hai (schema <code>Money</code>, jaise <code>"1250.5000"</code>) — kabhi float nahi. Currency document ya company ke ISO-4217 <code>currency</code> code se pata chalti hai.</li>
<li>Quantities, rates aur hours bhi exact decimal strings hain (schema <code>Decimal</code>). Request mein decimals <b>string</b> ke taur par bhejein (jaise <code>"quantity": "2.5000"</code>) taake float rounding na ho.</li>
<li><b>Timestamps</b> ISO-8601 UTC mein: <code>2026-10-15T09:30:00.000000Z</code>. <b>Plain dates</b>: <code>YYYY-MM-DD</code>.</li>
<li>Company ka <code>timezone</code> sirf display ke liye hai; API hamesha UTC deti/leti hai.</li>
</ul>
</section>

<section class="chapter" id="ch-rate"><h1><span class="kicker">Shuruaat</span>Rate limits</h1>
<table class="t"><thead><tr><th>Limiter</th><th>Limit</th><th>Kahan lagta hai</th></tr></thead><tbody>
<tr><td><code>api</code></td><td>240 / minute per user (anonymous: per IP)</td><td>Har route</td></tr>
<tr><td><code>login</code></td><td>5 / minute per email+IP, 30 / minute per IP</td><td><code>POST /auth/login</code></td></tr>
<tr><td><code>auth</code></td><td>10 / minute per IP</td><td>Forgot/reset password, confirm-password, e-mail verification</td></tr>
<tr><td><code>two-factor</code></td><td>5 / minute per pending 2FA session</td><td><code>/auth/2fa/challenge</code>, <code>/auth/2fa/confirm</code></td></tr>
<tr><td><code>exports</code></td><td>10 / minute per user</td><td>Report/data exports</td></tr>
<tr><td><code>search</code> (<code>60,1</code>)</td><td>60 / minute</td><td><code>GET /search</code></td></tr>
</tbody></table>
<p>Limit hit hone par <b>429</b> <code>throttled</code> milta hai, <code>Retry-After</code> header ke saath (aur <code>X-RateLimit-Limit</code> / <code>X-RateLimit-Remaining</code>). Har endpoint card par uske limiters "Throttle" row mein likhe hain (spec ka <code>x-throttle</code>).</p>
</section>

<section class="chapter" id="ch-files"><h1><span class="kicker">Shuruaat</span>File uploads &amp; downloads</h1>
<h3>Uploads (multipart)</h3>
<p>File wale endpoints <code>multipart/form-data</code> lete hain (company logo, employee photo/documents, employment contract, leave attachment, product image, documents aur versions, expense receipt). Server MIME type, extension aur size validate karta hai; allowed types aur max size har card ke body table mein likhe hain (jaise documents: pdf, png, jpg, jpeg, webp, doc, docx, xls, xlsx, csv, txt — max 10240 KB). Sensitive files private disk par rehti hain. Multipart par bhi <code>X-XSRF-TOKEN</code> aur <code>X-Company-Id</code> zaroori hain.</p>
<pre class="code">curl -s -c cookies.txt -b cookies.txt -H "Accept: application/json" \\
  -H "Origin: http://localhost:3000" -H "Referer: http://localhost:3000/" \\
  -H "X-XSRF-TOKEN: $XSRF" -H "X-Company-Id: 1" \\
  -F "file=@contract.pdf" -F "title=Signed contract" -F "category=contract" \\
  ${BASE}/employees/7/documents</pre>
<p class="muted small">Note: example ki <code>category</code> value <code>HRDocumentCategory</code> enum (Appendix B) se honi chahiye.</p>
<h3>Downloads</h3>
<p>Download endpoints file stream karte hain (<code>Content-Disposition: attachment</code>) — policy check ke baad, aur kai audited hain. Agar file naye browser tab mein kholni ho (jahan custom header nahi bhej sakte), to <code>X-Company-Id</code> ki jagah <code>company_id</code> query parameter use karein:</p>
<pre class="code">GET ${BASE}/invoices/41/pdf?company_id=1</pre>
<h3>Report exports</h3>
<p><code>POST /reports/{key}/export</code> body <code>{"format": "csv" | "xlsx" | "pdf", "params": ...}</code>. Chhota export seedha file (<code>200</code>) deta hai; bada export <code>202</code> ke saath <code>ExportJob</code> deta hai — <code>GET /report-exports/{exportJob}</code> se status dekhein aur <code>/download</code> se file lein. Expired export <b>410</b> <code>http.410</code> deta hai.</p>
</section>

<section class="chapter" id="ch-version"><h1><span class="kicker">Shuruaat</span>Versioning</h1>
<p>Saare endpoints <code>/api/v1</code> prefix ke neeche hain (resource-oriented, plural nouns; actions sub-POST ki shakal mein, jaise <code>/invoices/{id}/approve</code>). Is reference mein paths <code>/api/v1</code> ke baad wale hisse ke saath likhe gaye hain; card header mein poora path dikhaya gaya hai. Breaking changes ek naye version prefix ke saath aayenge; <code>v1</code> ke andar fields add ho sakte hain, is liye clients ko unknown fields ignore karne chahiye.</p>
<p>Spec code se generate hoti hai (<code>backend/tools/openapi/gen.php</code>): routes, FormRequests, API Resources, Enums aur migrations se. <code>PUT|PATCH</code> ke liye register shuda routes do operations ki shakal mein document hue hain jo ek hi handler share karte hain.</p>
</section>
`;

// ───────────────────────── appendices ─────────────────────────
function appendixSchemas() {
  let h = `<section class="chapter" id="app-schemas"><h1><span class="kicker">Appendix A</span>Schemas</h1>
<p>Spec ke <code>components.schemas</code> ke saare object (aur primitive) schemas. Enum-only schemas Appendix B mein hain. Nested objects ek level tak <code>parent.field</code> ki shakal mein dikhaye gaye hain.</p><div class="cols2">`;
  for (const [name, raw] of Object.entries(S).sort(([a], [b]) => a.localeCompare(b))) {
    if (isEnumSchema(raw)) continue;
    const s = mergeAllOf(raw);
    h += `<div class="schema" id="schema-${name}"><h3>${esc(name)}</h3>`;
    if (raw.description) h += `<p class="small">${md(raw.description)}</p>`;
    if (raw['x-resource-class']) h += `<p class="muted small">Source: <code>${esc(raw['x-resource-class'])}</code></p>`;
    if (!s.properties) {
      h += `<p class="small">Type: ${typeLabel(raw)} ${constraints(raw, { withDesc: false })}</p></div>`;
      continue;
    }
    const req = new Set(s.required || []);
    let rowsHtml = '';
    let anyNotes = false;
    for (const [k, v] of Object.entries(s.properties)) {
      const notes = [req.has(k) ? '<b>required</b>' : '', constraints(v)].filter(Boolean).join(' · ');
      const row = (cls, nm, t, n) => { if (n) anyNotes = true; return `<tr${cls}><td><code>${nm}</code></td><td>${t}</td><td class="nc">${n}</td></tr>`; };
      rowsHtml += row('', esc(k), typeLabel(v), notes);
      if (!v.$ref && v.properties) for (const [k2, v2] of Object.entries(v.properties)) rowsHtml += row(' class="sub"', `${esc(k)}.${esc(k2)}`, typeLabel(v2), constraints(v2));
      if (!v.$ref && v.items && !v.items.$ref && v.items.properties) for (const [k2, v2] of Object.entries(v.items.properties)) rowsHtml += row(' class="sub"', `${esc(k)}[].${esc(k2)}`, typeLabel(v2), constraints(v2));
    }
    if (!anyNotes) rowsHtml = rowsHtml.split('<td class="nc"></td>').join('');
    h += `<table class="t${anyNotes ? '' : ' nonotes'}"><thead><tr><th>Field</th><th>Type</th>${anyNotes ? '<th>Notes</th>' : ''}</tr></thead><tbody>${rowsHtml}</tbody></table></div>`;
  }
  return h + '</div></section>';
}

function appendixEnums() {
  let h = `<section class="chapter" id="app-enums"><h1><span class="kicker">Appendix B</span>Enums</h1>
<p>Allowed values (<code>app/Enums/**</code> se). Request mein inhi exact strings ko bhejein.</p>
<table class="t"><thead><tr><th style="width:28%">Enum</th><th>Allowed values</th></tr></thead><tbody>`;
  for (const [name, s] of Object.entries(S).sort(([a], [b]) => a.localeCompare(b))) {
    if (!isEnumSchema(s)) continue;
    h += `<tr id="enum-${name}"><td><code>${esc(name)}</code>${s['x-enum-class'] ? `<div class="muted tiny">${esc(s['x-enum-class'])}</div>` : ''}</td><td>${s.enum.map((v) => `<code>${esc(v)}</code>`).join(' ')}</td></tr>`;
  }
  return h + '</tbody></table></section>';
}

function appendixPermissions() {
  const idx = new Map();
  for (const o of ops) for (const p of o.op['x-permissions'] || []) { if (!idx.has(p)) idx.set(p, []); idx.get(p).push(o); }
  let h = `<section class="chapter" id="app-perms"><h1><span class="kicker">Appendix C</span>Permission slugs → endpoints</h1>
<p>${idx.size} permission slugs. Har slug ke saath woh endpoints jin ke <code>x-permissions</code> mein ye slug hai (route middleware ya policy). Route par kai slugs hon to koi ek kaafi hai. Path par click karke endpoint card khulta hai.</p><div class="cols2">`;
  for (const [p, list] of [...idx.entries()].sort(([a], [b]) => a.localeCompare(b))) {
    h += `<div class="permblk"><h4 id="perm-${slug(p)}"><span class="perm">${esc(p)}</span> <span class="muted small">${list.length} endpoint${list.length > 1 ? 's' : ''}</span></h4><ul class="plist">`;
    for (const o of list) h += `<li>${badge(o.method)} <a class="mono" href="#${opAnchor(o.op)}">${pathHtml(o.p)}</a></li>`;
    h += '</ul></div>';
  }
  return h + '</div></section>';
}

// ───────────────────────── TOC & cover ─────────────────────────
function toc() {
  const chapters = [
    ['ch-intro', "Ta'aruf (Introduction)"], ['ch-auth', 'Authentication flow'], ['ch-tenancy', 'Tenancy: X-Company-Id'],
    ['ch-perms', 'Permissions model'], ['ch-envelope', 'Request/response envelope & errors'], ['ch-lists', 'Pagination, search, sort, filter'],
    ['ch-money', 'Money, numbers & dates'], ['ch-rate', 'Rate limits'], ['ch-files', 'File uploads & downloads'], ['ch-version', 'Versioning'],
  ];
  let h = `<section class="chapter" id="toc"><h1>Fehrist (Table of contents)</h1><div class="toc">
<h3>1 · Shuruaat</h3><ol class="tl">${chapters.map(([id, t]) => `<li><a href="#${id}">${esc(t)}</a></li>`).join('')}</ol>
<h3>2 · Endpoint reference</h3><table class="t toct"><thead><tr><th>Module</th><th>Operations</th><th>GET</th><th>POST</th><th>PUT/PATCH</th><th>DELETE</th></tr></thead><tbody>`;
  for (const [tag, list] of byTag) {
    if (!list.length) continue;
    const c = (ms) => list.filter((o) => ms.includes(o.method)).length;
    h += `<tr><td><a href="#tag-${slug(tag)}">${esc(tag)}</a></td><td><b>${list.length}</b></td><td>${c(['get'])}</td><td>${c(['post'])}</td><td>${c(['put', 'patch'])}</td><td>${c(['delete'])}</td></tr>`;
  }
  h += `<tr class="tot"><td>Total</td><td><b>${totalOps}</b></td><td>${ops.filter((o) => o.method === 'get').length}</td><td>${ops.filter((o) => o.method === 'post').length}</td><td>${ops.filter((o) => ['put', 'patch'].includes(o.method)).length}</td><td>${ops.filter((o) => o.method === 'delete').length}</td></tr></tbody></table>
<h3>3 · Appendices</h3><ol class="tl" type="A"><li><a href="#app-schemas">Schemas</a></li><li><a href="#app-enums">Enums</a></li><li><a href="#app-perms">Permission slugs → endpoints</a></li></ol>
</div></section>`;
  return h;
}

const cover = `<section class="cover">
<div class="cv-top">ENTERPRISE ERP · DEVELOPER DOCUMENTATION</div>
<h1 class="cv-title">Enterprise ERP —<br>API Reference</h1>
<p class="cv-sub">REST API ka mukammal hawala: authentication, tenancy, permissions aur har endpoint ki tafseel — Hinglish mein.</p>
<table class="cv-meta"><tbody>
<tr><th>Version</th><td>${esc(VERSION)} (OpenAPI ${esc(spec.openapi)})</td></tr>
<tr><th>Date</th><td>${TODAY}</td></tr>
<tr><th>Base URL (local)</th><td><code>${BASE}</code></td></tr>
<tr><th>Base URL (production example)</th><td><code>${PROD}</code></td></tr>
<tr><th>Coverage</th><td>${pathsCount} paths · ${totalOps} operations · ${Object.keys(S).length} schemas</td></tr>
</tbody></table>
<div class="cv-foot">Source: <code>docs/api/openapi.yaml</code> (generated from code by <code>backend/tools/openapi/gen.php</code>) · ${esc(spec.info.license?.name || '')}</div>
</section>`;

// ───────────────────────── CSS & assembly ─────────────────────────
const CSS = `
@page { size: A4; margin: 18mm 14mm 16mm 14mm; }
:root { --accent:#4f46e5; --accent-soft:#eef2ff; --ink:#111827; --muted:#6b7280; --line:#e5e7eb; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif; font-size: 10pt; line-height: 1.45; color: var(--ink); margin: 0; background:#fff; }
code, .mono, pre { font-family: Consolas, "Cascadia Mono", "Courier New", monospace; }
code { font-size: 0.9em; background: #f3f4f6; padding: 0 3px; border-radius: 3px; word-break: break-word; }
a { color: var(--accent); text-decoration: none; }
h1, h2, h3, h4 { color: var(--ink); line-height: 1.2; break-after: avoid; }
h1 { font-size: 20pt; margin: 0 0 10pt; padding-bottom: 6pt; border-bottom: 2.5pt solid var(--accent); }
h1 .kicker { display:block; font-size: 8.5pt; letter-spacing: .12em; text-transform: uppercase; color: var(--accent); font-weight: 700; margin-bottom: 3pt; }
h1 code { font-size: 0.85em; }
h2 { font-size: 13pt; margin: 14pt 0 6pt; }
h3 { font-size: 11pt; margin: 12pt 0 4pt; color: #312e81; }
h4 { font-size: 10pt; margin: 8pt 0 3pt; }
p { margin: 4pt 0 6pt; }
ul, ol { margin: 3pt 0 6pt; padding-left: 16pt; }
li { margin: 1.5pt 0; }
.chapter { break-before: page; }
.lead { font-size: 10.5pt; background: var(--accent-soft); border-left: 3pt solid var(--accent); padding: 6pt 9pt; border-radius: 3pt; }
.muted { color: var(--muted); }
.small { font-size: 8.5pt; }
.tiny { font-size: 7pt; }
pre.code { background: #0f172a; color: #e2e8f0; font-size: 8pt; line-height: 1.4; padding: 7pt 9pt; border-radius: 4pt; white-space: pre-wrap; word-break: break-word; margin: 4pt 0 8pt; break-inside: avoid; }
table { border-collapse: collapse; width: 100%; }
table.t { font-size: 8.5pt; margin: 3pt 0 8pt; }
table.t th, table.t td { border: 0.5pt solid var(--line); padding: 2pt 4pt; vertical-align: top; text-align: left; }
table.t thead th { background: #f5f5ff; color: #312e81; font-weight: 600; }
table.t thead { display: table-header-group; }
table.t tr { break-inside: avoid; }
table.t tbody th { background: #fafafa; width: 30%; font-weight: 600; }
table.t td code { background: transparent; padding: 0; color: #1e1b4b; }
table.t tr.sub td { background: #fcfcff; }
table.t tr.sub td:first-child { padding-left: 12pt; }
table.ov td:nth-child(1) { width: 52pt; }
.op table.t td:first-child { width: 27%; }
.op table.t td:nth-child(2) { width: 17%; }
table.ov td:nth-child(2) { width: 40%; font-size: 8pt; word-break: break-all; }
.req { color: #b91c1c; }
.m { display: inline-block; min-width: 40pt; text-align: center; font-family: Consolas, monospace; font-weight: 700; font-size: 7.5pt; color: #fff; padding: 1.5pt 4pt; border-radius: 3pt; letter-spacing: .03em; }
.m-get { background: #16a34a; } .m-post { background: #2563eb; } .m-put, .m-patch { background: #d97706; } .m-delete { background: #dc2626; }
.perm { display: inline-block; font-family: Consolas, monospace; font-size: 7.8pt; background: var(--accent-soft); color: #3730a3; border: 0.5pt solid #c7d2fe; padding: 0 3pt; border-radius: 2.5pt; margin: 0.5pt 0; }
.pp { color: #9333ea; }
/* operation cards */
h2.ops-h { margin-top: 12pt; color: var(--accent); }
.op { border: 0.75pt solid #d4d4f7; border-left: 3pt solid var(--accent); border-radius: 4pt; padding: 6pt 8pt 4pt; margin: 0 0 9pt; }
.op.keep { break-inside: avoid; }
.op-head, .op-title, .hi, table.kv { break-after: avoid; }
.op-head { display: flex; align-items: center; gap: 6pt; }
.op-head .path { font-family: Consolas, monospace; font-size: 9.5pt; font-weight: 600; word-break: break-all; }
.op-head .base { color: var(--muted); font-weight: 400; }
.op-title { font-weight: 600; font-size: 10pt; margin-top: 3pt; }
.opid { font-family: Consolas, monospace; font-weight: 400; font-size: 7.5pt; color: var(--muted); margin-left: 4pt; }
.hi { margin: 2pt 0 3pt; color: #1f2937; }
.note { font-size: 8.5pt; background: #f9fafb; border: 0.5pt dashed #d1d5db; padding: 3pt 6pt; border-radius: 3pt; margin: 3pt 0; color: #374151; }
.warn { font-size: 8.5pt; background: #fffbeb; border: 0.5pt solid #fcd34d; padding: 3pt 6pt; border-radius: 3pt; margin: 3pt 0; }
table.kv { font-size: 8.5pt; margin: 3pt 0; }
table.kv th { width: 62pt; text-align: left; color: var(--muted); font-weight: 600; vertical-align: top; padding: 1pt 4pt 1pt 0; }
table.kv td { padding: 1pt 0; }
.sec { font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #4338ca; margin: 6pt 0 2pt; break-after: avoid; }
.sec .ct { text-transform: none; letter-spacing: 0; font-weight: 400; font-family: Consolas, monospace; color: var(--muted); margin-left: 4pt; }
ul.resp { list-style: none; padding: 0; margin: 0 0 2pt; font-size: 8.5pt; }
ul.resp li { margin: 1.5pt 0; }
ul.resp li.errs code { background: #fef2f2; color: #991b1b; }
.fields { margin: 2pt 0 2pt 10pt; line-height: 1.6; }
.f { display: inline-block; margin-right: 8pt; white-space: nowrap; font-size: 8pt; }
.f i { color: var(--muted); font-style: normal; font-size: 7.5pt; }
.tag-inf { font-size: 7pt; background: #fef3c7; color: #92400e; padding: 0 3pt; border-radius: 2pt; white-space: nowrap; }
.schema h3 + p, .schema h3 { break-after: avoid; }
.cols2 { columns: 2; column-gap: 7mm; }
.cols2 .schema { break-inside: avoid-column; }
.cols2 table.t { font-size: 7.5pt; }
.cols2 table.t td:first-child { width: 38%; word-break: break-word; }
.cols2 table.t td:nth-child(2) { width: 30%; }
.cols2 table.t.nonotes td:first-child { width: 55%; }
.cols2 .schema h3 { font-size: 9.5pt; margin: 6pt 0 2pt; }
.schema h3 { margin-top: 10pt; font-family: Consolas, monospace; }
.permblk { break-inside: avoid; }
ul.plist { list-style: none; padding-left: 4pt; font-size: 7.8pt; margin: 0 0 4pt; }
.cols2 .permblk { break-inside: avoid-column; }
.cols2 .permblk h4 { margin: 5pt 0 1pt; }
ul.plist li { margin: 0.5pt 0; word-break: break-all; }
ul.plist .m { min-width: 34pt; font-size: 6.8pt; }
.toc ol.tl li { font-size: 10.5pt; margin: 3pt 0; }
table.toct td:not(:first-child), table.toct th:not(:first-child) { text-align: center; width: 13%; }
table.toct tr.tot td { background: var(--accent-soft); font-weight: 600; }
/* cover */
.cover { height: 255mm; display: flex; flex-direction: column; justify-content: center; padding: 0 6mm; position: relative; }
.cover::before { content: ""; position: absolute; left: 0; top: 0; bottom: 0; width: 6pt; background: var(--accent); border-radius: 3pt; }
.cv-top { font-size: 9pt; letter-spacing: .2em; color: var(--accent); font-weight: 700; margin-bottom: 14pt; }
.cv-title { font-size: 34pt; border: none; margin: 0 0 12pt; padding: 0; line-height: 1.1; }
.cv-sub { font-size: 13pt; color: #374151; max-width: 150mm; margin-bottom: 24pt; }
table.cv-meta { width: auto; font-size: 10pt; }
table.cv-meta th { text-align: left; color: var(--muted); font-weight: 600; padding: 3pt 14pt 3pt 0; }
table.cv-meta td { padding: 3pt 0; }
.cv-foot { position: absolute; bottom: 0; font-size: 8pt; color: var(--muted); }
`;

let body = cover + toc() + GETTING_STARTED;
for (const [tag, list] of byTag) if (list.length) body += renderTag(tag, list);
body += appendixSchemas() + appendixEnums() + appendixPermissions();

const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Enterprise ERP — API Reference</title><style>${CSS}</style></head><body>${body}</body></html>`;
fs.writeFileSync(HTML_OUT, html);

const renderedCards = (html.match(/<div class="op( keep)?" id="op-/g) || []).length;
const uniqueIds = new Set([...html.matchAll(/<div class="op(?: keep)?" id="(op-[^"]+)"/g)].map((m) => m[1])).size;
console.log(`HTML: ${HTML_OUT} (${(html.length / 1024).toFixed(0)} KB) — operations in spec: ${totalOps}, cards rendered: ${renderedCards} (unique ids ${uniqueIds})`);
if (renderedCards !== totalOps) { console.error('Card count mismatch!'); process.exitCode = 1; }

// ───────────────────────── PDF ─────────────────────────
const headerTemplate = `<div style="font-family:'Segoe UI',Arial,sans-serif;font-size:7.5pt;color:#6b7280;width:100%;padding:0 14mm;display:flex;justify-content:space-between;"><span style="color:#4f46e5;font-weight:600;">Enterprise ERP — API Reference</span><span>v${esc(VERSION)} · /api/v1</span></div>`;
const footerTemplate = `<div style="font-family:'Segoe UI',Arial,sans-serif;font-size:7.5pt;color:#6b7280;width:100%;padding:0 14mm;display:flex;justify-content:space-between;"><span>Enterprise ERP — API Reference</span><span>Page <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>`;

const browser = await chromium.launch({ executablePath: EDGE, headless: true });
try {
  const page = await browser.newPage();
  page.setDefaultTimeout(0);
  await page.goto(pathToFileURL(HTML_OUT).href, { waitUntil: 'load', timeout: 0 });
  await page.pdf({
    path: PDF_OUT, format: 'A4', printBackground: true, displayHeaderFooter: true,
    headerTemplate, footerTemplate, outline: true,
    margin: { top: '18mm', bottom: '16mm', left: '14mm', right: '14mm' },
  });
} finally {
  await browser.close();
}
const buf = fs.readFileSync(PDF_OUT);
const pages = (buf.toString('latin1').match(/\/Type\s*\/Page(?!s)/g) || []).length;
console.log(`PDF: ${PDF_OUT} — ${pages} pages, ${(buf.length / 1024 / 1024).toFixed(2)} MB`);
