/**
 * Turns technical audit action codes (`holidays.created`,
 * `performance.reviews.submitted`, `payroll.run_approved`) into plain
 * sentences anyone can read ("Added a holiday", "Submitted a performance
 * review", "Approved a payroll run"). Exact phrases win; everything else is
 * built from "<thing>.<verb>" so new modules read sensibly without changes.
 */

const EXACT: Record<string, string> = {
  'auth.login': 'Signed in',
  'auth.logout': 'Signed out',
  'auth.login_failed': 'Failed sign-in attempt',
  'auth.login_blocked_inactive': 'Sign-in blocked (account deactivated)',
  'auth.password_changed': 'Changed their password',
  'auth.password_reset': 'Reset their password',
  'auth.password_reset_requested': 'Asked for a password reset link',
  'auth.permission_denied': 'Tried to open something without permission',
  'auth.reauth': 'Confirmed their password',
  'auth.reauth_failed': 'Entered a wrong password when confirming',
  'auth.switch_company': 'Switched to another company',
  'auth.switch_company_denied': 'Tried to switch to a company they don’t belong to',
  'auth.email_verified': 'Verified their email address',
  'auth.two_factor_enabled': 'Turned on two-step sign-in',
  'auth.two_factor_disabled': 'Turned off two-step sign-in',
  'auth.two_factor_failed': 'Entered a wrong two-step sign-in code',
  'auth.two_factor_recovery_regenerated': 'Made new two-step backup codes',
  'auth.two_factor_recovery_used': 'Signed in with a two-step backup code',
  'attendance.bulk_recorded': 'Recorded attendance for several people',
  'users.invited': 'Invited a new user',
  'users.invite_resent': 'Sent an invitation again',
  'users.membership_updated': 'Changed a user’s access',
  'users.membership_removed': 'Removed a user from the company',
  'roles.permissions_changed': 'Changed what a role can do',
  'companies.provisioned': 'Set up a new company',
  'finance.defaults_seeded': 'Set up the default accounts',
  'inventory.adjusted': 'Corrected stock quantities',
  'inventory.adjustment_posted': 'Posted a stock correction',
  'inventory.transfer_shipped': 'Sent a stock transfer',
  'inventory.transfer_received': 'Received a stock transfer',
  'inventory.transfer_cancelled': 'Cancelled a stock transfer',
  'leave.balances.generated': 'Created yearly leave balances',
  'leave.balances.adjusted': 'Adjusted a leave balance',
  'payments.payment_recorded': 'Recorded a payment to a supplier',
  'payments.receipt_recorded': 'Recorded money received from a customer',
  'tax.tables.brackets_replaced': 'Updated the tax brackets',
  'reports.export_downloaded': 'Downloaded a report',
  'platform.owner_created': 'Created the platform owner account',
  'platform.user_updated': 'Changed a platform user',
  'periods.closed': 'Closed an accounting period',
  'periods.reopened': 'Reopened an accounting period',
  'deliveries.delivered': 'Marked a delivery as delivered',
  'companies.logo_updated': 'Changed the company logo',
  'employees.photo_updated': 'Changed an employee’s photo',
  'payroll.payslip_downloaded': 'Downloaded a payslip',
  'payroll.exported': 'Exported payroll to a file',
  'payroll.run_created': 'Started a payroll run',
  'payroll.run_paid': 'Marked a payroll run as paid',
  'invoices.exported': 'Exported invoices to a file',
  'attendance.deleted': 'Deleted an attendance record',
  'documents.share_link_created': 'Created a share link for a document',
  'documents.version_added': 'Uploaded a new version of a document',
  'employment_contracts.document_downloaded': 'Downloaded an employment contract file',
  'employee_documents.downloaded': 'Downloaded an employee document',
  'platform.company_created': 'Created a company',
};

/** Everyday names for the "thing" part (keys are the code with dots/underscores as spaces). */
const NOUNS: Record<string, string> = {
  'sales orders': 'sales order',
  'journal entries': 'journal entry',
  journals: 'journal entry',
  'expense claims': 'expense claim',
  'employee salaries': 'employee salary',
  'units of measure': 'unit of measure',
  'pay components': 'pay component',
  'bank accounts': 'bank account',
  'financial periods': 'accounting period',
  'financial years': 'financial year',
  'goods receipts': 'goods receipt',
  'purchase orders': 'purchase order',
  'purchase requisitions': 'purchase request',
  'purchase returns': 'return to supplier',
  'stock adjustments': 'stock correction',
  'stock transfers': 'stock transfer',
  'leave requests': 'leave request',
  'leave types': 'leave type',
  'leave balances': 'leave balance',
  'performance reviews': 'performance review',
  'employment contracts': 'employment contract',
  'employee documents': 'employee document',
  'document folders': 'document folder',
  'customer contacts': 'customer contact',
  'customer communications': 'customer note',
  'supplier contacts': 'supplier contact',
  'pipeline stages': 'sales pipeline stage',
  'product categories': 'product category',
  'project members': 'project member',
  'project milestones': 'project milestone',
  'project expenses': 'project expense',
  'time entries': 'timesheet entry',
  'tax rates': 'tax rate',
  'tax tables': 'tax table',
  'salary structures': 'salary structure',
  'platform company': 'company',
  'payroll run': 'payroll run',
  payroll: 'payroll',
  opportunities: 'sales opportunity',
  quotations: 'quotation',
  deliveries: 'delivery',
  companies: 'company',
  categories: 'category',
};

const VERBS: Record<string, string> = {
  created: 'Added',
  updated: 'Edited',
  deleted: 'Deleted',
  approved: 'Approved',
  submitted: 'Sent % for approval',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
  voided: 'Voided',
  posted: 'Posted',
  reversed: 'Reversed',
  sent: 'Sent',
  accepted: 'Accepted',
  converted: 'Converted',
  confirmed: 'Confirmed',
  delivered: 'Delivered',
  shipped: 'Shipped',
  received: 'Received',
  closed: 'Closed',
  reopened: 'Reopened',
  exported: 'Exported',
  downloaded: 'Downloaded',
  uploaded: 'Uploaded',
  generated: 'Created',
  adjusted: 'Adjusted',
  assigned: 'Assigned',
  recorded: 'Recorded',
  paid: 'Paid',
  calculated: 'Calculated',
  finalized: 'Finalised',
  acknowledged: 'Acknowledged',
  ordered: 'Ordered',
  activated: 'Activated',
  deactivated: 'Deactivated',
  added: 'Added',
  removed: 'Removed',
  replaced: 'Replaced',
  changed: 'Changed',
  invited: 'Invited',
};

function singular(word: string): string {
  if (/ies$/.test(word)) return word.replace(/ies$/, 'y');
  if (/(ss|sh|ch|x)es$/.test(word)) return word.replace(/es$/, '');
  if (/s$/.test(word) && !/ss$/.test(word)) return word.slice(0, -1);
  return word;
}

function noun(raw: string): string {
  const key = raw.replace(/[._]/g, ' ').trim();
  if (NOUNS[key]) return NOUNS[key];
  const words = key.split(' ');
  words[words.length - 1] = singular(words[words.length - 1] ?? '');
  return words.join(' ');
}

// 'a user', 'a unit' (sounds like 'you'); 'an employee', 'an invoice'.
const article = (n: string) => (/^[aeiou]/i.test(n) && !/^(us|uni|eu)/i.test(n) ? 'an' : 'a');

/** "holidays.created" → "Added a holiday". Falls back to a tidy version of the code. */
export function auditActionLabel(action: string): string {
  if (EXACT[action]) return EXACT[action];

  const parts = action.split('.');
  const last = parts.pop() ?? '';
  const [verbKey, ...rest] = last.split('_').reverse();
  const verb = VERBS[verbKey ?? ''];
  // "run_approved" → extra noun "run"; "payment_recorded" under "payments" → no duplicate.
  const extra = rest.reverse().join(' ');
  let thing = parts.filter((p) => p !== 'platform').join(' ');
  if (extra && singular(thing.split(' ').pop() ?? '') !== extra) thing = `${singular(thing)} ${extra}`.trim();
  else if (extra && !thing) thing = extra;

  if (verb && thing) {
    const n = noun(thing);
    const obj = `${article(n)} ${n}`;
    return verb.includes('%') ? verb.replace('%', obj) : `${verb} ${obj}`;
  }
  const tidy = action.replace(/[._]/g, ' ');
  return tidy.charAt(0).toUpperCase() + tidy.slice(1);
}

/** Lower-case form for use mid-sentence: "Olivia added a holiday". */
export function auditActionPhrase(action: string): string {
  const label = auditActionLabel(action);
  return label.charAt(0).toLowerCase() + label.slice(1);
}
