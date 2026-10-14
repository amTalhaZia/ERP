import { get } from '@/lib/api/client';
import { resource } from '@/lib/crud/resource';
import type { Money } from '@/lib/api/types';

export type AccountType = 'asset' | 'liability' | 'equity' | 'revenue' | 'expense';

export interface Account {
  id: number;
  parent_id: number | null;
  code: string;
  name: string;
  type: AccountType;
  subtype: string | null;
  normal_balance: 'debit' | 'credit';
  is_bank: boolean;
  is_cash: boolean;
  is_system: boolean;
  is_active: boolean;
  currency: string;
  description: string | null;
  parent?: { id: number; code: string; name: string } | null;
  lines_count?: number;
}

export interface JournalLine {
  id?: number;
  line_no?: number;
  account_id: number;
  account?: { id: number; code: string; name: string };
  description: string | null;
  debit: Money;
  credit: Money;
}

export type JournalStatus = 'draft' | 'posted' | 'reversed';

export interface JournalEntry {
  id: number;
  number: string;
  entry_date: string;
  period?: { id: number; name: string } | null;
  status: JournalStatus;
  reference: string | null;
  memo: string | null;
  source_type: string | null;
  source_id: number | null;
  total_debit: Money;
  total_credit: Money;
  is_balanced: boolean;
  creator?: { id: number; name: string } | null;
  poster?: { id: number; name: string } | null;
  posted_at: string | null;
  reverses_entry_id: number | null;
  reversed_by_entry_id: number | null;
  lines?: JournalLine[];
  lines_count?: number;
}

export interface FinancialPeriod {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  status: 'open' | 'closed';
  closed_by?: { id: number; name: string } | null;
  closed_at: string | null;
  financial_year?: { id: number; name: string };
}

export interface TaxRate {
  id: number;
  name: string;
  code: string;
  rate: string;
  type: 'sales' | 'purchase' | 'both';
  account?: { id: number; code: string; name: string } | null;
  is_active: boolean;
}

export interface BankAccount {
  id: number;
  name: string;
  bank_name: string | null;
  account_number_masked: string | null;
  iban_masked: string | null;
  swift: string | null;
  currency: string;
  is_active: boolean;
  account?: { id: number; code: string; name: string };
}

export type JournalInput = {
  entry_date: string;
  reference?: string | null;
  memo?: string | null;
  lines: { account_id: number; description?: string | null; debit: string; credit: string }[];
};

export const accounts = resource<Account, Partial<Account>>('accounts', 'Account');
export const journals = resource<JournalEntry, JournalInput>('journal-entries', 'Journal entry');
export const periods = resource<FinancialPeriod>('financial-periods', 'Period');
export const taxRates = resource<TaxRate, Partial<TaxRate>>('tax-rates', 'Tax rate');
export const bankAccounts = resource<BankAccount, Record<string, unknown>>('bank-accounts', 'Bank account');

// ---- Reports ---------------------------------------------------------------

export interface ReportAccountRow {
  account_id: number;
  code: string;
  name: string;
  amount: Money;
}
export interface ReportSection {
  groups: { subtype: string | null; accounts: ReportAccountRow[]; total: Money }[];
  total: Money;
}
export interface TrialBalance {
  as_of: string;
  rows: { account_id: number; code: string; name: string; type: AccountType; debit_total: Money; credit_total: Money; balance: Money }[];
  totals: { debit_total: Money; credit_total: Money; balanced: boolean };
  comparison?: TrialBalance;
}
export interface ProfitAndLoss {
  from: string;
  to: string;
  revenue: ReportSection;
  cost_of_sales: ReportSection;
  gross_profit: Money;
  expenses: ReportSection;
  total_expenses: Money;
  net_profit: Money;
  comparison?: ProfitAndLoss;
}
export interface BalanceSheet {
  as_of: string;
  assets: ReportSection;
  liabilities: ReportSection;
  equity: ReportSection & { retained_earnings_prior_years: Money; current_year_earnings: Money; total_equity: Money };
  total_liabilities_and_equity: Money;
  check: { balanced: boolean; difference: Money };
  comparison?: BalanceSheet;
}
export interface CashFlowSection {
  lines: { account_id: number; code: string; name: string; amount: Money; direction: 'inflow' | 'outflow' }[];
  inflows: Money;
  outflows: Money;
  net: Money;
}
export interface CashFlow {
  from: string;
  to: string;
  opening_cash: Money;
  operating: CashFlowSection;
  investing: CashFlowSection;
  financing: CashFlowSection;
  net_change: Money;
  closing_cash: Money;
}

const report = <T>(name: string, params: Record<string, unknown>) =>
  get<{ data: T }>(`/finance/reports/${name}`, params).then((r) => r.data);

export const financeReports = {
  trialBalance: (p: Record<string, unknown>) => report<TrialBalance>('trial-balance', p),
  profitAndLoss: (p: Record<string, unknown>) => report<ProfitAndLoss>('profit-and-loss', p),
  balanceSheet: (p: Record<string, unknown>) => report<BalanceSheet>('balance-sheet', p),
  cashFlow: (p: Record<string, unknown>) => report<CashFlow>('cash-flow', p),
};
