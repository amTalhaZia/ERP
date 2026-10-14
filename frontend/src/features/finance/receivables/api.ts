import { useQuery } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';
import type { Money, Paginated, Single } from '@/lib/api/types';
import { useAppSelector } from '@/store';
import type { Account } from '@/features/finance/api';

// ---- Enums -------------------------------------------------------------------

export type DocumentStatus = 'draft' | 'pending_approval' | 'approved' | 'partially_paid' | 'paid' | 'void';
export type ExpenseClaimStatus = 'draft' | 'submitted' | 'approved' | 'rejected' | 'paid';
export type PaymentType = 'receipt' | 'payment';
export type PartnerType = 'customer' | 'supplier' | 'employee';
export type PaymentMethod = 'cash' | 'bank_transfer' | 'cheque' | 'card' | 'other';
export type PaymentStatus = 'posted' | 'void';
export type TaxRateType = 'sales' | 'purchase' | 'both';
export type AllocationType = 'invoice' | 'bill' | 'expense_claim';

export const DOCUMENT_STATUSES: { value: DocumentStatus | 'overdue'; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'pending_approval', label: 'Pending approval' },
  { value: 'approved', label: 'Approved' },
  { value: 'partially_paid', label: 'Partially paid' },
  { value: 'paid', label: 'Paid' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'void', label: 'Void' },
];
export const PAYMENT_METHODS: { value: PaymentMethod; label: string }[] = [
  { value: 'bank_transfer', label: 'Bank transfer' },
  { value: 'cash', label: 'Cash' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'card', label: 'Card' },
  { value: 'other', label: 'Other' },
];
export const EXPENSE_STATUSES: { value: ExpenseClaimStatus; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'submitted', label: 'Submitted' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'paid', label: 'Paid' },
];

export const isEditableStatus = (s: DocumentStatus) => s === 'draft' || s === 'pending_approval';
export const isOpenStatus = (s: DocumentStatus) => s === 'approved' || s === 'partially_paid';

// ---- Documents ---------------------------------------------------------------

export interface PartnerRef {
  id: number;
  name: string | null;
}

export interface DocumentLine {
  id: number;
  line_no: number;
  product_id: number | null;
  description: string;
  quantity: Money;
  unit_price: Money;
  discount_percent: Money;
  tax_rate_id: number | null;
  tax_rate: Money;
  account_id: number | null;
  account?: { id: number; code: string; name: string } | null;
  line_subtotal: Money;
  discount_amount: Money;
  tax_amount: Money;
  line_total: Money;
}

interface DocumentBase {
  id: number;
  company_id: number;
  number: string;
  due_date: string;
  currency: string;
  status: DocumentStatus;
  is_overdue: boolean;
  subtotal: Money;
  discount_total: Money;
  tax_total: Money;
  total: Money;
  amount_paid: Money;
  balance_due: Money;
  notes: string | null;
  branch_id: number | null;
  branch?: { id: number; name: string } | null;
  journal_entry_id: number | null;
  created_by: number | null;
  approved_by: number | null;
  approver?: { id: number; name: string } | null;
  approved_at: string | null;
  voided_at: string | null;
  void_reason: string | null;
  lines?: DocumentLine[];
  lines_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface Invoice extends DocumentBase {
  customer_id: number;
  customer?: PartnerRef;
  sales_order_id: number | null;
  invoice_date: string;
  terms: string | null;
  creator?: { id: number; name: string } | null;
}

export interface Bill extends DocumentBase {
  supplier_id: number;
  supplier?: PartnerRef;
  purchase_order_id: number | null;
  supplier_reference: string;
  bill_date: string;
}

export interface DocumentLineInput {
  product_id?: number | null;
  description: string;
  quantity: string;
  unit_price: string;
  discount_percent?: string | null;
  tax_rate_id?: number | null;
  account_id?: number | null;
}

export interface InvoiceInput {
  customer_id: number;
  invoice_date: string;
  due_date?: string | null;
  branch_id?: number | null;
  notes?: string | null;
  terms?: string | null;
  lines: DocumentLineInput[];
}

export interface BillInput {
  supplier_id: number;
  supplier_reference: string;
  bill_date: string;
  due_date?: string | null;
  branch_id?: number | null;
  notes?: string | null;
  lines: DocumentLineInput[];
}

// ---- Payments ----------------------------------------------------------------

export interface PaymentAllocation {
  id: number;
  type: AllocationType;
  document_id: number;
  amount: Money;
}

export interface Payment {
  id: number;
  company_id: number;
  number: string;
  type: PaymentType;
  partner_type: PartnerType;
  partner_id: number;
  partner?: PartnerRef;
  payment_date: string;
  method: PaymentMethod;
  bank_account_id: number;
  bank_account?: { id: number; name: string; account_number: string | null } | null;
  amount: Money;
  currency: string;
  reference: string | null;
  notes: string | null;
  status: PaymentStatus;
  branch_id: number | null;
  journal_entry_id: number | null;
  created_by: number | null;
  voided_at: string | null;
  void_reason: string | null;
  allocations?: PaymentAllocation[];
  created_at: string | null;
}

export interface PaymentInput {
  type?: PaymentType;
  partner_type: PartnerType;
  partner_id: number;
  payment_date: string;
  method: PaymentMethod;
  bank_account_id: number;
  amount: string;
  reference?: string | null;
  notes?: string | null;
  branch_id?: number | null;
  allocations: { type: AllocationType; id: number; amount: string }[];
}

// ---- Expense claims ----------------------------------------------------------

export interface ExpenseClaimLine {
  id: number;
  line_no: number;
  expense_date: string;
  category: string;
  account_id: number;
  description: string | null;
  amount: Money;
  tax_rate_id: number | null;
  tax_rate: Money;
  tax_amount: Money;
  line_total: Money;
  has_receipt: boolean;
  receipt_name: string | null;
}

export interface ExpenseClaim {
  id: number;
  company_id: number;
  number: string;
  employee_id: number | null;
  user_id: number;
  user?: { id: number; name: string } | null;
  claim_date: string;
  currency: string;
  status: ExpenseClaimStatus;
  subtotal: Money;
  tax_total: Money;
  total: Money;
  amount_paid: Money;
  balance_due: Money;
  notes: string | null;
  branch_id: number | null;
  submitted_at: string | null;
  approver_id: number | null;
  approver?: { id: number; name: string } | null;
  approved_at: string | null;
  rejection_reason: string | null;
  journal_entry_id: number | null;
  lines?: ExpenseClaimLine[];
  lines_count?: number;
  created_at: string | null;
}

export interface ExpenseClaimInput {
  claim_date: string;
  branch_id?: number | null;
  notes?: string | null;
  lines: { expense_date: string; category: string; account_id: number; description?: string | null; amount: string; tax_rate_id?: number | null }[];
}

// ---- Tax rates & bank accounts (full shapes) ---------------------------------

export interface TaxRateFull {
  id: number;
  name: string;
  code: string;
  rate: Money;
  type: TaxRateType;
  account_id: number | null;
  account?: { id: number; code: string; name: string } | null;
  is_compound: boolean;
  is_active: boolean;
}

export interface TaxRateInput {
  name: string;
  code: string;
  rate: string;
  type: TaxRateType;
  account_id: number | null;
  is_compound: boolean;
  is_active: boolean;
}

export interface BankAccountFull {
  id: number;
  account_id: number;
  account?: { id: number; code: string; name: string };
  name: string;
  bank_name: string | null;
  account_number_masked: string | null;
  account_number_last4: string | null;
  iban_masked: string | null;
  swift: string | null;
  currency: string;
  opening_balance: Money;
  is_active: boolean;
}

export interface BankAccountInput {
  account_id?: number;
  name?: string;
  bank_name?: string | null;
  account_number?: string | null;
  iban?: string | null;
  swift?: string | null;
  currency?: string | null;
  opening_balance?: string | null;
  is_active?: boolean;
}

// ---- Resources ---------------------------------------------------------------

export const invoices = resource<Invoice, InvoiceInput>('invoices', 'Invoice');
export const bills = resource<Bill, BillInput>('bills', 'Bill');
export const payments = resource<Payment, PaymentInput>('payments', 'Payment');
export const expenseClaims = resource<ExpenseClaim, ExpenseClaimInput>('expense-claims', 'Expense claim');
export const taxRatesFull = resource<TaxRateFull, TaxRateInput>('tax-rates', 'Tax rate');
export const bankAccountsFull = resource<BankAccountFull, BankAccountInput>('bank-accounts', 'Bank account');

// ---- Reports -----------------------------------------------------------------

export interface AgingRow {
  partner_id: number;
  partner_name: string | null;
  documents: number;
  current: Money;
  days_1_30: Money;
  days_31_60: Money;
  days_61_90: Money;
  over_90: Money;
  total: Money;
}
export type AgingBucket = 'current' | 'days_1_30' | 'days_31_60' | 'days_61_90' | 'over_90';
export const AGING_BUCKETS: { key: AgingBucket; label: string; color: string }[] = [
  { key: 'current', label: 'Current', color: 'var(--chart-1)' },
  { key: 'days_1_30', label: '1–30 days', color: 'var(--chart-2)' },
  { key: 'days_31_60', label: '31–60 days', color: 'var(--chart-3)' },
  { key: 'days_61_90', label: '61–90 days', color: 'var(--chart-4)' },
  { key: 'over_90', label: '> 90 days', color: 'var(--chart-5)' },
];
export interface AgingReport {
  as_of: string;
  rows: AgingRow[];
  totals: Record<AgingBucket | 'total', Money> & { documents: number };
}

export interface ReceivablesSummary {
  as_of: string;
  receivables: { outstanding: Money; open_count: number; overdue_amount: Money; overdue_count: number };
  payables: { outstanding: Money; open_count: number; overdue_amount: Money; overdue_count: number };
  expense_claims_payable: { outstanding: Money; count: number };
  average_days_to_pay: string | null;
  dso_days: string | null;
}

export interface StatementEntry {
  date: string;
  type: 'invoice' | 'receipt';
  id: number;
  number: string;
  due_date?: string;
  reference?: string | null;
  debit: Money;
  credit: Money;
  balance: Money;
}
export interface CustomerStatement {
  customer: { id: number; code?: string | null; name?: string | null; email?: string | null; currency?: string | null };
  from: string;
  to: string;
  opening_balance: Money;
  entries: StatementEntry[];
  total_debits: Money;
  total_credits: Money;
  closing_balance: Money;
}

export const receivablesReports = {
  arAging: (asOf?: string) => get<{ data: AgingReport }>('/finance/reports/ar-aging', { as_of: asOf || undefined }).then((r) => r.data),
  apAging: (asOf?: string) => get<{ data: AgingReport }>('/finance/reports/ap-aging', { as_of: asOf || undefined }).then((r) => r.data),
  summary: (asOf?: string) => get<{ data: ReceivablesSummary }>('/finance/reports/receivables-summary', { as_of: asOf || undefined }).then((r) => r.data),
  customerStatement: (customerId: number, from?: string, to?: string) =>
    get<{ data: CustomerStatement }>(`/finance/customer-statements/${customerId}`, { from: from || undefined, to: to || undefined }).then((r) => r.data),
};

// ---- Files -------------------------------------------------------------------

function saveBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** Server filename from Content-Disposition, if present. */
function filenameFrom(disposition: unknown, fallback: string) {
  if (typeof disposition !== 'string') return fallback;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (star?.[1]) return decodeURIComponent(star[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain?.[1] ?? fallback;
}

export async function downloadInvoicePdf(invoice: Pick<Invoice, 'id' | 'number'>) {
  const res = await api.get<Blob>(`/invoices/${invoice.id}/pdf`, { responseType: 'blob' });
  saveBlob(res.data, filenameFrom(res.headers['content-disposition'], `${invoice.number}.pdf`));
}

export async function downloadExpenseReceipt(claimId: number, line: Pick<ExpenseClaimLine, 'id' | 'receipt_name'>) {
  const res = await api.get<Blob>(`/expense-claims/${claimId}/lines/${line.id}/receipt`, { responseType: 'blob' });
  saveBlob(res.data, filenameFrom(res.headers['content-disposition'], line.receipt_name ?? `receipt-${line.id}`));
}

export async function uploadExpenseReceipt(claimId: number, lineId: number, file: File) {
  const form = new FormData();
  form.append('receipt', file);
  const res = await api.post<Single<ExpenseClaim>>(`/expense-claims/${claimId}/lines/${lineId}/receipt`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return res.data.data;
}

// ---- Lookups -----------------------------------------------------------------

export const useCompanyId = () => useAppSelector((s) => s.session.current_company?.id ?? 0);
export const useCurrency = () => useAppSelector((s) => s.session.current_company?.currency ?? 'USD');

export interface PartnerOption {
  id: number;
  name: string;
  code?: string | null;
  currency?: string | null;
  payment_terms_days?: number | null;
}

/** Active customers (`/customers/all`) or suppliers (`/suppliers/all`). */
export function usePartners(kind: 'customer' | 'supplier', opts: { enabled?: boolean } = {}) {
  const companyId = useCompanyId();
  const path = kind === 'customer' ? '/customers/all' : '/suppliers/all';
  return useQuery({
    queryKey: [companyId, kind === 'customer' ? 'customers' : 'suppliers', 'all'],
    queryFn: () => get<{ data: PartnerOption[] }>(path).then((r) => r.data),
    staleTime: 60_000,
    enabled: opts.enabled ?? true,
  });
}

export const partnerLabel = (p: PartnerOption) => (p.code ? `${p.code} · ${p.name}` : p.name);

/** Active tax rates, optionally restricted to sales (sales+both) or purchase (purchase+both). */
export function useTaxRateOptions(scope: 'sales' | 'purchase' | 'all' = 'all', opts: { enabled?: boolean } = {}) {
  const q = taxRatesFull.useList({ per_page: 100, sort: 'code', 'filter[is_active]': 'true' }, opts);
  const rows = (q.data?.data ?? []).filter((t) => scope === 'all' || t.type === 'both' || t.type === scope);
  return { query: q, rows, byId: new Map(rows.map((t) => [t.id, t])) };
}

/** Active GL accounts of the given type(s), e.g. 'revenue' or 'expense,asset'. */
export function useAccountOptions(types: string, opts: { enabled?: boolean; filter?: (a: Account) => boolean } = {}) {
  const companyId = useCompanyId();
  const params = { per_page: 100, sort: 'code', 'filter[type]': types, 'filter[is_active]': 'true' };
  const q = useQuery({
    queryKey: [companyId, 'accounts', 'list', params],
    queryFn: () => get<Paginated<Account>>('/accounts', params),
    staleTime: 60_000,
    enabled: opts.enabled ?? true,
  });
  const rows = (q.data?.data ?? []).filter(opts.filter ?? (() => true));
  return { query: q, rows, options: rows.map((a) => ({ value: String(a.id), label: `${a.code} · ${a.name}` })) };
}

export function useBankAccountOptions(opts: { enabled?: boolean } = {}) {
  const q = bankAccountsFull.useList({ per_page: 100, sort: 'name', 'filter[is_active]': 'true' }, opts);
  const rows = q.data?.data ?? [];
  return {
    query: q,
    rows,
    options: rows.map((b) => ({ value: String(b.id), label: `${b.name}${b.account_number_masked ? ` (${b.account_number_masked})` : ''} · ${b.currency}` })),
  };
}

// ---- Open documents for payment allocation -----------------------------------

export interface OpenDocument {
  type: AllocationType;
  id: number;
  number: string;
  date: string;
  due_date: string | null;
  total: Money;
  balance_due: Money;
  currency: string;
}

/** A partner's documents that can still receive a payment, oldest due first. */
export async function fetchOpenDocuments(partnerType: PartnerType, partnerId: number): Promise<OpenDocument[]> {
  if (partnerType === 'customer') {
    const r = await get<Paginated<Invoice>>('/invoices', { per_page: 100, sort: 'due_date', 'filter[customer_id]': partnerId, 'filter[status]': 'approved,partially_paid' });
    return r.data.map((d) => ({ type: 'invoice', id: d.id, number: d.number, date: d.invoice_date, due_date: d.due_date, total: d.total, balance_due: d.balance_due, currency: d.currency }));
  }
  if (partnerType === 'supplier') {
    const r = await get<Paginated<Bill>>('/bills', { per_page: 100, sort: 'due_date', 'filter[supplier_id]': partnerId, 'filter[status]': 'approved,partially_paid' });
    return r.data.map((d) => ({ type: 'bill', id: d.id, number: d.number, date: d.bill_date, due_date: d.due_date, total: d.total, balance_due: d.balance_due, currency: d.currency }));
  }
  const r = await get<Paginated<ExpenseClaim>>('/expense-claims', { per_page: 100, sort: 'claim_date', 'filter[user_id]': partnerId, 'filter[status]': 'approved' });
  return r.data.map((d) => ({ type: 'expense_claim', id: d.id, number: d.number, date: d.claim_date, due_date: null, total: d.total, balance_due: d.balance_due, currency: d.currency }));
}

/**
 * Employees who can be paid: owners of approved (unpaid) expense claims.
 * (Company users require users.view; claimants are what payments settle.)
 */
export function useClaimants(opts: { enabled?: boolean } = {}) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, 'expense-claims', 'claimants'],
    queryFn: async () => {
      const r = await get<Paginated<ExpenseClaim>>('/expense-claims', { per_page: 100, 'filter[status]': 'approved' });
      const map = new Map<number, PartnerOption>();
      for (const c of r.data) if (c.user) map.set(c.user.id, { id: c.user.id, name: c.user.name });
      return [...map.values()].sort((a, b) => a.name.localeCompare(b.name));
    },
    staleTime: 30_000,
    enabled: opts.enabled ?? true,
  });
}

export const ALLOCATION_HREF: Record<AllocationType, (id: number) => string> = {
  invoice: (id) => `/finance/invoices/${id}`,
  bill: (id) => `/finance/bills/${id}`,
  expense_claim: (id) => `/finance/expenses/${id}`,
};
