'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, get } from '@/lib/api/client';
import type { ApiError } from '@/lib/api/errors';
import type { Money, Paginated } from '@/lib/api/types';
import { resource } from '@/lib/crud/resource';
import { useAppSelector } from '@/store';

/**
 * Payroll API contract (backend routes/api/v1/payroll.php + Resources/Payroll).
 * Money is always a decimal string. Nothing here is persisted beyond the
 * TanStack Query in-memory cache, and amounts are never logged.
 */

// ---- Enums ---------------------------------------------------------------------

export type RunStatus = 'draft' | 'calculated' | 'approved' | 'finalized' | 'paid' | 'cancelled';
export type ComponentKind = 'earning' | 'deduction' | 'employer_contribution';
export type CalculationMethod = 'fixed' | 'percent_of_base' | 'percent_of_gross';
export type TaxTablePeriod = 'monthly' | 'annual';
export type SkipReason = 'no_salary' | 'currency_mismatch' | 'already_in_run' | (string & {});
export type PayslipWarning = 'no_tax_table' | 'no_working_days' | 'negative_net' | (string & {});

export const RUN_STATUSES: { value: RunStatus; label: string }[] = [
  { value: 'draft', label: 'Draft' },
  { value: 'calculated', label: 'Calculated' },
  { value: 'approved', label: 'Approved' },
  { value: 'finalized', label: 'Finalized' },
  { value: 'paid', label: 'Paid' },
  { value: 'cancelled', label: 'Cancelled' },
];
export const KINDS: { value: ComponentKind; label: string }[] = [
  { value: 'earning', label: 'Earning' },
  { value: 'deduction', label: 'Deduction' },
  { value: 'employer_contribution', label: 'Employer contribution' },
];
export const CALCULATIONS: { value: CalculationMethod; label: string }[] = [
  { value: 'fixed', label: 'Fixed amount' },
  { value: 'percent_of_base', label: '% of base salary' },
  { value: 'percent_of_gross', label: '% of gross' },
];
export const TAX_PERIODS: { value: TaxTablePeriod; label: string }[] = [
  { value: 'monthly', label: 'Monthly income' },
  { value: 'annual', label: 'Annual income (annualised ×12)' },
];
export const RESERVED_CODES = ['BASE', 'TAX'];

export const kindLabel = (k: string) => KINDS.find((x) => x.value === k)?.label ?? k;
export const calcLabel = (c: string | null | undefined) => CALCULATIONS.find((x) => x.value === c)?.label ?? '—';

export const SKIP_REASONS: Record<string, string> = {
  no_salary: 'No salary effective for this period',
  currency_mismatch: 'Salary currency differs from the run currency',
  already_in_run: 'Already included in another run for this period',
};
export const WARNINGS: Record<string, { label: string; help: string }> = {
  no_tax_table: { label: 'No tax table', help: 'No active tax table applied — tax was calculated as 0.' },
  no_working_days: { label: 'No working days', help: 'The period has no working days for this employee; the base was not prorated.' },
  negative_net: { label: 'Negative net', help: 'Deductions exceed earnings. Adjust deductions and recalculate before approval.' },
};

// ---- Types ---------------------------------------------------------------------

type Ref = { id: number; name: string } | null;

export interface RunTotals {
  gross: Money;
  tax: Money;
  deductions: Money;
  employer_contributions: Money;
  net: Money;
  employee_count: number;
}

export interface PayrollRun {
  id: number;
  number: string;
  name: string;
  period_start: string;
  period_end: string;
  payment_date: string;
  currency: string;
  status: RunStatus;
  branch_id: number | null;
  branch?: Ref;
  department_id: number | null;
  department?: Ref;
  totals: RunTotals;
  calculated_at: string | null;
  created_by?: Ref;
  approved_by?: Ref;
  approved_at: string | null;
  finalized_by?: Ref;
  finalized_at: string | null;
  paid_at: string | null;
  bank_account_id: number | null;
  cancelled_at: string | null;
  journal_entry_id: number | null;
  payment_journal_entry_id: number | null;
  notes: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface SkippedEmployee {
  employee_id: number;
  employee_code: string;
  name: string;
  reason: SkipReason;
}

export interface PayslipLine {
  code: string;
  name: string;
  kind: ComponentKind;
  calculation: CalculationMethod | null;
  rate: Money | null;
  amount: Money;
  is_taxable: boolean;
}

export interface Payslip {
  id: number;
  number: string;
  status: RunStatus;
  payroll_run_id: number;
  run?: { id: number; number: string; name: string; period_start: string; period_end: string; payment_date: string };
  employee: {
    id: number;
    code: string;
    name: string;
    department: string | null;
    designation: string | null;
    bank_name: string | null;
    bank_account: string | null;
  };
  currency: string;
  base_salary: Money;
  working_days: string;
  unpaid_days: string;
  paid_days: string;
  earned_base: Money;
  gross: Money;
  taxable: Money;
  tax: Money;
  total_deductions: Money;
  employer_contributions: Money;
  net: Money;
  warnings: PayslipWarning[];
  lines?: PayslipLine[];
  created_at: string | null;
  updated_at: string | null;
}

export interface PayComponent {
  id: number;
  code: string;
  name: string;
  description: string | null;
  kind: ComponentKind;
  calculation: CalculationMethod;
  default_amount: Money;
  default_rate: string;
  is_taxable: boolean;
  reduces_taxable: boolean;
  affects_gross: boolean;
  gl_account_code: string | null;
  effective_gl_account_code: string;
  sort_order: number;
  is_active: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface ComponentOverride {
  pay_component_id: number;
  code?: string | null;
  name?: string | null;
  kind?: ComponentKind | null;
  calculation?: CalculationMethod | null;
  amount: Money | null;
  rate: string | null;
}

export interface SalaryStructure {
  id: number;
  name: string;
  description: string | null;
  currency: string;
  is_active: boolean;
  components?: ComponentOverride[];
  components_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface TaxBracket {
  id?: number;
  from_amount: Money;
  to_amount: Money | null;
  rate: string;
  fixed_amount: Money;
}

export interface TaxTable {
  id: number;
  name: string;
  effective_from: string;
  period: TaxTablePeriod;
  is_active: boolean;
  notes: string | null;
  brackets?: TaxBracket[];
  brackets_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface EmployeeSalary {
  id: number;
  employee_id: number;
  salary_structure_id: number | null;
  salary_structure?: Ref;
  base_salary: Money;
  currency: string;
  effective_from: string;
  effective_to: string | null;
  pay_frequency: 'monthly';
  notes: string | null;
  components?: ComponentOverride[];
  created_by?: Ref;
  created_at: string | null;
}

export interface SummaryRow {
  gross: Money;
  tax: Money;
  deductions: Money;
  employer_contributions: Money;
  net: Money;
}
export interface PayrollSummary {
  from: string;
  to: string;
  runs: (SummaryRow & { id: number; number: string; name: string; period_start: string; period_end: string; status: RunStatus; currency: string; employee_count: number })[];
  departments: (SummaryRow & { department_id: number | null; department: string; payslips: number; employees: number })[];
  totals: SummaryRow & { runs: number; payslips: number; headcount_paid: number; average_net: Money };
}
export interface ComponentsReport {
  run: { id: number; number: string; status: RunStatus; currency: string };
  components: { code: string; name: string; kind: ComponentKind; payslips: number; total: Money }[];
}

// ---- Inputs ----------------------------------------------------------------------

export interface RunInput {
  name: string;
  period_start: string;
  period_end: string;
  payment_date: string;
  branch_id: number | null;
  department_id: number | null;
  notes: string | null;
}
export interface OverrideInput {
  pay_component_id: number;
  amount: string | null;
  rate: string | null;
}

// ---- Resources -------------------------------------------------------------------

export const payrollRuns = resource<PayrollRun, RunInput>('payroll-runs', 'Payroll run');
export const payComponents = resource<PayComponent, Record<string, unknown>>('pay-components', 'Pay component');
export const salaryStructures = resource<SalaryStructure, Record<string, unknown>>('salary-structures', 'Salary structure');
export const taxTables = resource<TaxTable, Record<string, unknown>>('tax-tables', 'Tax table');

export const useCompanyId = () => useAppSelector((s) => s.session.current_company?.id ?? 0);
export const useCompanyCurrency = () => useAppSelector((s) => s.session.current_company?.currency ?? 'USD');

/** Every payroll-related cache key (runs, payslips, reports, journals, bank). */
const PAYROLL_KEYS = ['payroll-runs', 'payslips', 'my-payslips', 'payroll-reports', 'journal-entries', 'bank-accounts', 'employee-salaries'];
export function useInvalidatePayroll() {
  const qc = useQueryClient();
  return () => qc.invalidateQueries({ predicate: (q) => q.queryKey.some((k) => typeof k === 'string' && PAYROLL_KEYS.includes(k)) });
}

// ---- Runs --------------------------------------------------------------------------

interface RunEnvelope {
  data: PayrollRun;
  meta?: { skipped?: SkippedEmployee[] };
}
export interface RunResult {
  run: PayrollRun;
  skipped: SkippedEmployee[];
}
const toResult = (r: RunEnvelope): RunResult => ({ run: r.data, skipped: r.meta?.skipped ?? [] });

export const payrollApi = {
  createRun: (body: RunInput) => api.post<RunEnvelope>('/payroll-runs', body).then((r) => toResult(r.data)),
  calculate: (id: number) => api.post<RunEnvelope>(`/payroll-runs/${id}/calculate`, {}).then((r) => toResult(r.data)),
  approve: (id: number) => api.post<RunEnvelope>(`/payroll-runs/${id}/approve`, {}).then((r) => toResult(r.data)),
  finalize: (id: number) => api.post<RunEnvelope>(`/payroll-runs/${id}/finalize`, {}).then((r) => toResult(r.data)),
  pay: (id: number, body: { bank_account_id: number; paid_at: string }) => api.post<RunEnvelope>(`/payroll-runs/${id}/pay`, body).then((r) => toResult(r.data)),
  cancel: (id: number, reason: string | null) => api.post<RunEnvelope>(`/payroll-runs/${id}/cancel`, { reason }).then((r) => toResult(r.data)),
  remove: (id: number) => api.delete(`/payroll-runs/${id}`).then(() => undefined),
  payslips: (runId: number, params: Record<string, unknown>) => get<Paginated<Payslip>>(`/payroll-runs/${runId}/payslips`, params),
  payslip: (id: number, mine: boolean) => get<{ data: Payslip }>(mine ? `/my-payslips/${id}` : `/payslips/${id}`).then((r) => r.data),
  myPayslips: (params: Record<string, unknown>) => get<Paginated<Payslip>>('/my-payslips', params),
  salaries: (employeeId: number) => get<Paginated<EmployeeSalary>>(`/employees/${employeeId}/salaries`, { per_page: 100 }),
  recordSalary: (employeeId: number, body: Record<string, unknown>) =>
    api.post<{ data: EmployeeSalary }>(`/employees/${employeeId}/salaries`, body).then((r) => r.data.data),
  summary: (from: string, to: string) => get<{ data: PayrollSummary }>('/payroll/reports/summary', { from, to }).then((r) => r.data),
  components: (runId: number) => get<{ data: ComponentsReport }>('/payroll/reports/components', { run_id: runId }).then((r) => r.data),
};

/** Run state transitions: invalidate payroll caches; errors surface to the caller. */
export function useRunMutation<TVars>(fn: (vars: TVars) => Promise<RunResult | void>) {
  const invalidate = useInvalidatePayroll();
  return useMutation<RunResult | void, ApiError, TVars>({
    mutationFn: fn,
    onSuccess: () => void invalidate(),
  });
}

export function useRunPayslips(runId: number, params: Record<string, unknown>) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, 'payroll-runs', runId, 'payslips', params],
    queryFn: () => payrollApi.payslips(runId, params),
    placeholderData: (prev) => prev,
  });
}

export function usePayslip(id: number | null, mine: boolean) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, mine ? 'my-payslips' : 'payslips', 'one', id],
    queryFn: () => payrollApi.payslip(id!, mine),
    enabled: id !== null,
  });
}

// ---- Files -------------------------------------------------------------------------

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

function filenameFrom(disposition: unknown, fallback: string) {
  if (typeof disposition !== 'string') return fallback;
  const star = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  if (star?.[1]) return decodeURIComponent(star[1]);
  const plain = /filename="?([^";]+)"?/i.exec(disposition);
  return plain?.[1] ?? fallback;
}

export async function downloadPayslipPdf(slip: Pick<Payslip, 'id' | 'number'>, mine: boolean) {
  const res = await api.get<Blob>(mine ? `/my-payslips/${slip.id}/pdf` : `/payslips/${slip.id}/pdf`, { responseType: 'blob' });
  saveBlob(res.data, filenameFrom(res.headers['content-disposition'], `${slip.number}.pdf`));
}

export async function downloadRunExport(run: Pick<PayrollRun, 'id' | 'number'>, format: 'csv' | 'xlsx') {
  const res = await api.get<Blob>(`/payroll-runs/${run.id}/export`, { params: { format }, responseType: 'blob' });
  saveBlob(res.data, filenameFrom(res.headers['content-disposition'], `${run.number}.${format}`));
}

// ---- Lookups -----------------------------------------------------------------------

interface BankAccountLite {
  id: number;
  name: string;
  bank_name: string | null;
  account_number_masked: string | null;
  currency: string;
  is_active: boolean;
}
export function useBankAccountOptions(enabled = true) {
  const companyId = useCompanyId();
  const q = useQuery({
    queryKey: [companyId, 'bank-accounts', 'payroll-options'],
    queryFn: () => get<Paginated<BankAccountLite>>('/bank-accounts', { per_page: 100 }),
    staleTime: 60_000,
    enabled,
  });
  const rows = (q.data?.data ?? []).filter((b) => b.is_active);
  return { query: q, rows, options: rows.map((b) => ({ value: String(b.id), label: `${b.name}${b.account_number_masked ? ` · ${b.account_number_masked}` : ''} (${b.currency})` })) };
}

interface AccountLite {
  id: number;
  code: string;
  name: string;
  type: string;
  is_active: boolean;
}
export function useGlAccountOptions(enabled = true) {
  const companyId = useCompanyId();
  const q = useQuery({
    queryKey: [companyId, 'accounts', 'payroll-options'],
    queryFn: () => get<Paginated<AccountLite>>('/accounts', { per_page: 100, sort: 'code' }),
    staleTime: 60_000,
    enabled,
  });
  const rows = (q.data?.data ?? []).filter((a) => a.is_active);
  return { query: q, options: rows.map((a) => ({ value: a.code, label: `${a.code} · ${a.name}` })) };
}

export function useDepartmentOptions(enabled = true) {
  const companyId = useCompanyId();
  const q = useQuery({
    queryKey: [companyId, 'departments', 'payroll-options'],
    queryFn: () => get<Paginated<{ id: number; name: string }>>('/departments', { per_page: 100, sort: 'name' }),
    staleTime: 60_000,
    enabled,
  });
  return { query: q, options: (q.data?.data ?? []).map((d) => ({ value: String(d.id), label: d.name })) };
}

export interface EmployeeLite {
  id: number;
  employee_code: string;
  name: string;
  status?: string;
  department?: { id: number; name: string } | null;
}
export function useEmployeeSearch(search: string, enabled = true) {
  const companyId = useCompanyId();
  return useQuery({
    queryKey: [companyId, 'employees', 'payroll-picker', search],
    queryFn: () => get<Paginated<EmployeeLite>>('/employees', { per_page: 50, search: search || undefined, sort: 'last_name' }),
    placeholderData: (prev) => prev,
    staleTime: 30_000,
    enabled,
  });
}

export function usePayComponentOptions(enabled = true) {
  const q = payComponents.useList({ per_page: 100, sort: 'sort_order' }, { enabled });
  const rows = q.data?.data ?? [];
  return { query: q, rows, byId: new Map(rows.map((c) => [c.id, c])) };
}
