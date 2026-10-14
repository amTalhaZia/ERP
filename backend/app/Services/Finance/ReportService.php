<?php

namespace App\Services\Finance;

use App\Enums\Finance\AccountSubtype;
use App\Enums\Finance\AccountType;
use App\Enums\Finance\JournalEntryStatus;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\FinancialYear;
use App\Support\Money\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements straight from posted journal lines (never from
 * sub-ledgers). Every method is one or two GROUP BY queries; money is returned
 * as DECIMAL(18,4) strings. Balances are signed by the account's natural side:
 * debit-normal (asset/expense) = debit − credit, otherwise credit − debit.
 *
 * Account `opening_balance` is informational; opening positions are expected
 * to be posted as an opening-balance journal entry so the ledger always ties.
 */
class ReportService
{
    /**
     * @return array{as_of: string, branch_id: ?int, rows: list<array<string, mixed>>, totals: array{debit_total: string, credit_total: string, balanced: bool}}
     */
    public function trialBalance(Company $company, string $asOf, ?int $branchId = null, bool $includeZero = false): array
    {
        $rows = $this->movements($company->id, null, $asOf, $branchId)
            ->filter(fn (object $m) => $includeZero || ! Money::isZero($m->debit_total) || ! Money::isZero($m->credit_total))
            ->map(fn (object $m) => [
                'account_id' => (int) $m->account_id,
                'code' => $m->code,
                'name' => $m->name,
                'type' => $m->type,
                'subtype' => $m->subtype,
                'debit_total' => Money::normalize($m->debit_total),
                'credit_total' => Money::normalize($m->credit_total),
                'balance' => $this->signed($m),
            ])->values();

        $debit = Money::sum($rows->pluck('debit_total')->all());
        $credit = Money::sum($rows->pluck('credit_total')->all());

        return [
            'as_of' => $asOf,
            'branch_id' => $branchId,
            'rows' => $rows->all(),
            'totals' => ['debit_total' => $debit, 'credit_total' => $credit, 'balanced' => Money::equals($debit, $credit)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        $movements = $this->movements($company->id, $from, $to, $branchId);

        $revenue = $this->section($movements->where('type', AccountType::Revenue->value));
        $costOfSales = $this->section($movements->where('type', AccountType::Expense->value)->where('subtype', AccountSubtype::CostOfSales->value));
        $expenses = $this->section($movements->where('type', AccountType::Expense->value)->where('subtype', '!=', AccountSubtype::CostOfSales->value));

        $grossProfit = Money::sub($revenue['total'], $costOfSales['total']);
        $netProfit = Money::sub($grossProfit, $expenses['total']);

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'revenue' => $revenue,
            'cost_of_sales' => $costOfSales,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'total_expenses' => Money::add($costOfSales['total'], $expenses['total']),
            'net_profit' => $netProfit,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function balanceSheet(Company $company, string $asOf, ?int $branchId = null): array
    {
        $movements = $this->movements($company->id, null, $asOf, $branchId);
        $fyStart = $this->fiscalYearStart($company, $asOf);

        $assets = $this->section($movements->where('type', AccountType::Asset->value));
        $liabilities = $this->section($movements->where('type', AccountType::Liability->value));
        $equityAccounts = $this->section($movements->where('type', AccountType::Equity->value));

        $priorEarnings = $this->netProfit($company->id, null, Carbon::parse($fyStart)->subDay()->toDateString(), $branchId);
        $currentEarnings = $this->netProfit($company->id, $fyStart, $asOf, $branchId);

        $equityTotal = Money::sum([$equityAccounts['total'], $priorEarnings, $currentEarnings]);
        $liabilitiesAndEquity = Money::add($liabilities['total'], $equityTotal);

        return [
            'as_of' => $asOf,
            'branch_id' => $branchId,
            'fiscal_year_start' => $fyStart,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equityAccounts + [
                'retained_earnings_prior_years' => $priorEarnings,
                'current_year_earnings' => $currentEarnings,
                'total_equity' => $equityTotal,
            ],
            'total_liabilities_and_equity' => $liabilitiesAndEquity,
            'check' => [
                'balanced' => Money::equals($assets['total'], $liabilitiesAndEquity),
                'difference' => Money::sub($assets['total'], $liabilitiesAndEquity),
            ],
        ];
    }

    /**
     * Direct-method cash flow: movements on is_cash/is_bank accounts, explained
     * by the counter-accounts of the same entries and classified by subtype.
     *
     * @return array<string, mixed>
     */
    public function cashFlow(Company $company, string $from, string $to, ?int $branchId = null): array
    {
        $opening = $this->cashPosition($company->id, Carbon::parse($from)->subDay()->toDateString(), $branchId);
        $closing = $this->cashPosition($company->id, $to, $branchId);

        $counter = $this->postedLines($company->id, $branchId)
            ->whereBetween('e.entry_date', [$from, $to])
            ->where('a.is_cash', false)->where('a.is_bank', false)
            ->whereExists(function (Builder $q): void {
                $q->selectRaw('1')
                    ->from('journal_lines as c')
                    ->join('accounts as ca', 'ca.id', '=', 'c.account_id')
                    ->whereColumn('c.journal_entry_id', 'e.id')
                    ->where(fn (Builder $w) => $w->where('ca.is_cash', true)->orWhere('ca.is_bank', true));
            })
            ->selectRaw('a.id as account_id, a.code, a.name, a.type, a.subtype, SUM(l.credit - l.debit) as amount')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.subtype')
            ->orderBy('a.code')
            ->get();

        $sections = ['operating' => [], 'investing' => [], 'financing' => []];

        foreach ($counter as $row) {
            $amount = Money::normalize($row->amount);

            if (Money::isZero($amount)) {
                continue;
            }

            $section = AccountSubtype::cashFlowSection(AccountType::from($row->type), $row->subtype);
            $sections[$section][] = [
                'account_id' => (int) $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'subtype' => $row->subtype,
                'amount' => $amount,
                'direction' => Money::isNegative($amount) ? 'outflow' : 'inflow',
            ];
        }

        $result = ['from' => $from, 'to' => $to, 'branch_id' => $branchId, 'opening_cash' => $opening];
        $net = Money::zero();

        foreach ($sections as $name => $lines) {
            $amounts = array_column($lines, 'amount');
            $inflows = Money::sum(array_filter($amounts, fn (string $a) => ! Money::isNegative($a)));
            $outflows = Money::sum(array_filter($amounts, fn (string $a) => Money::isNegative($a)));
            $total = Money::sum($amounts);
            $net = Money::add($net, $total);

            $result[$name] = ['lines' => $lines, 'inflows' => $inflows, 'outflows' => Money::abs($outflows), 'net' => $total];
        }

        $result['net_change'] = $net;
        $result['closing_cash'] = $closing;
        $result['reconciles'] = Money::equals(Money::add($opening, $net), $closing);

        return $result;
    }

    /**
     * Quick signed balances of every active account (dashboard widgets).
     *
     * @return array{as_of: string, accounts: list<array<string, mixed>>, totals: array<string, string>}
     */
    public function accountBalances(Company $company, string $asOf, ?int $branchId = null): array
    {
        $rows = $this->movements($company->id, null, $asOf, $branchId, onlyActive: true)->map(fn (object $m) => [
            'account_id' => (int) $m->account_id,
            'code' => $m->code,
            'name' => $m->name,
            'type' => $m->type,
            'subtype' => $m->subtype,
            'is_cash' => (bool) $m->is_cash,
            'is_bank' => (bool) $m->is_bank,
            'balance' => $this->signed($m),
        ])->values();

        $totals = [];
        foreach (AccountType::cases() as $type) {
            $totals[$type->value] = Money::sum($rows->where('type', $type->value)->pluck('balance')->all());
        }
        $totals['cash_and_bank'] = Money::sum($rows->filter(fn (array $r) => $r['is_cash'] || $r['is_bank'])->pluck('balance')->all());

        return ['as_of' => $asOf, 'accounts' => $rows->all(), 'totals' => $totals];
    }

    /**
     * General ledger for one account: opening balance before `from`, then the
     * posted lines in range with a running balance (window function, so the
     * balance is correct on every page).
     *
     * @return array{account: array<string, mixed>, from: ?string, to: ?string, opening_balance: string, closing_balance: string, lines: LengthAwarePaginator}
     */
    public function ledger(Company $company, Account $account, ?string $from, ?string $to, ?int $branchId, int $page, int $perPage): array
    {
        $sign = $account->isDebitNormal() ? 1 : -1;

        $opening = $from === null
            ? Money::zero()
            : $this->signedSum($this->postedLines($company->id, $branchId)->where('l.account_id', $account->id)->where('e.entry_date', '<', $from), $sign);

        $closing = $this->signedSum(
            $this->postedLines($company->id, $branchId)->where('l.account_id', $account->id)->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to)),
            $sign,
        );

        $inner = $this->postedLines($company->id, $branchId)
            ->where('l.account_id', $account->id)
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->selectRaw(
                'l.id, e.id as entry_id, e.number, e.entry_date, e.reference, e.memo, e.source_type, e.source_id, '
                .'l.line_no, l.description, l.debit, l.credit, l.partner_type, l.partner_id, l.branch_id, '
                .'SUM(l.debit - l.credit) OVER (ORDER BY e.entry_date, e.id, l.line_no, l.id) as running'
            );

        $paginator = DB::query()->fromSub($inner, 't')
            ->orderBy('t.entry_date')->orderBy('t.entry_id')->orderBy('t.line_no')->orderBy('t.id')
            ->paginate($perPage, ['*'], 'page', $page);

        $paginator->getCollection()->transform(fn (object $row) => [
            'id' => (int) $row->id,
            'entry_id' => (int) $row->entry_id,
            'number' => $row->number,
            'entry_date' => $row->entry_date,
            'reference' => $row->reference,
            'memo' => $row->memo,
            'source_type' => $row->source_type,
            'source_id' => $row->source_id === null ? null : (int) $row->source_id,
            'line_no' => (int) $row->line_no,
            'description' => $row->description,
            'debit' => Money::normalize($row->debit),
            'credit' => Money::normalize($row->credit),
            'partner_type' => $row->partner_type,
            'partner_id' => $row->partner_id === null ? null : (int) $row->partner_id,
            'branch_id' => $row->branch_id === null ? null : (int) $row->branch_id,
            'running_balance' => Money::add($opening, Money::mul(Money::normalize($row->running), (string) $sign)),
        ]);

        return [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type->value],
            'from' => $from,
            'to' => $to,
            'opening_balance' => $opening,
            'closing_balance' => $closing,
            'lines' => $paginator,
        ];
    }

    /**
     * Previous period of equal length, for `compare=true`.
     *
     * @return array{from: string, to: string}
     */
    public static function previousRange(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $days = $start->diffInDays(Carbon::parse($to)) + 1;

        return [
            'from' => $start->copy()->subDays($days)->toDateString(),
            'to' => $start->copy()->subDay()->toDateString(),
        ];
    }

    // -- Building blocks -----------------------------------------------------

    /**
     * Posted lines joined to entries and accounts, tenant + optional branch filter.
     */
    private function postedLines(int $companyId, ?int $branchId): Builder
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('l.company_id', $companyId)
            ->where('e.company_id', $companyId)
            // A Reversed entry stays on the ledger; its posted mirror nets it out.
            ->whereIn('e.status', [JournalEntryStatus::Posted->value, JournalEntryStatus::Reversed->value])
            ->when($branchId !== null, fn (Builder $q) => $q->whereRaw('COALESCE(l.branch_id, e.branch_id) = ?', [$branchId]));
    }

    /**
     * Per-account debit/credit totals for a date range.
     *
     * @return Collection<int, object>
     */
    private function movements(int $companyId, ?string $from, ?string $to, ?int $branchId, bool $onlyActive = false): Collection
    {
        return $this->postedLines($companyId, $branchId)
            ->when($from, fn (Builder $q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('e.entry_date', '<=', $to))
            ->when($onlyActive, fn (Builder $q) => $q->where('a.is_active', true))
            ->selectRaw('a.id as account_id, a.code, a.name, a.type, a.subtype, a.is_cash, a.is_bank, SUM(l.debit) as debit_total, SUM(l.credit) as credit_total')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.subtype', 'a.is_cash', 'a.is_bank')
            ->orderBy('a.code')
            ->get();
    }

    private function netProfit(int $companyId, ?string $from, ?string $to, ?int $branchId): string
    {
        $value = $this->postedLines($companyId, $branchId)
            ->whereIn('a.type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->when($from, fn (Builder $q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('e.entry_date', '<=', $to))
            ->selectRaw('COALESCE(SUM(l.credit - l.debit), 0) as net')
            ->value('net');

        return Money::normalize((string) $value);
    }

    private function cashPosition(int $companyId, string $asOf, ?int $branchId): string
    {
        $value = $this->postedLines($companyId, $branchId)
            ->where(fn (Builder $q) => $q->where('a.is_cash', true)->orWhere('a.is_bank', true))
            ->where('e.entry_date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as net')
            ->value('net');

        return Money::normalize((string) $value);
    }

    private function signedSum(Builder $query, int $sign): string
    {
        $value = Money::normalize((string) $query->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as net')->value('net'));

        return $sign === 1 ? $value : Money::negate($value);
    }

    private function signed(object $movement): string
    {
        $debit = Money::normalize($movement->debit_total);
        $credit = Money::normalize($movement->credit_total);

        return AccountType::from($movement->type)->isDebitNormal() ? Money::sub($debit, $credit) : Money::sub($credit, $debit);
    }

    /**
     * Accounts grouped by subtype with signed balances and totals.
     *
     * @param  Collection<int, object>  $movements
     * @return array{groups: list<array{subtype: ?string, accounts: list<array<string, mixed>>, total: string}>, total: string}
     */
    private function section(Collection $movements): array
    {
        $groups = $movements
            ->filter(fn (object $m) => ! Money::isZero($m->debit_total) || ! Money::isZero($m->credit_total))
            ->groupBy(fn (object $m) => (string) $m->subtype)
            ->map(function (Collection $items, string $subtype): array {
                $accounts = $items->map(fn (object $m) => [
                    'account_id' => (int) $m->account_id,
                    'code' => $m->code,
                    'name' => $m->name,
                    'amount' => $this->signed($m),
                ])->values()->all();

                return [
                    'subtype' => $subtype === '' ? null : $subtype,
                    'accounts' => $accounts,
                    'total' => Money::sum(array_column($accounts, 'amount')),
                ];
            })->values();

        return ['groups' => $groups->all(), 'total' => Money::sum($groups->pluck('total')->all())];
    }

    /** First day of the financial year containing $asOf (falls back to the company's fiscal month). */
    private function fiscalYearStart(Company $company, string $asOf): string
    {
        $year = FinancialYear::forCompany($company->id)
            ->where('start_date', '<=', $asOf)->where('end_date', '>=', $asOf)
            ->first();

        if ($year !== null) {
            return $year->start_date->toDateString();
        }

        $month = max(1, min(12, (int) ($company->fiscal_year_start_month ?: 1)));
        $date = Carbon::parse($asOf);
        $start = Carbon::create($date->year, $month, 1);

        return ($start->isAfter($date) ? $start->subYear() : $start)->toDateString();
    }
}
