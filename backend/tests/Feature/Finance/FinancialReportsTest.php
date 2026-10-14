<?php

namespace Tests\Feature\Finance;

use App\Models\Company;
use App\Models\User;
use App\Services\Finance\LedgerPoster;
use Tests\Feature\Finance\Concerns\InteractsWithLedger;
use Tests\TestCase;

/**
 * Known scenario (FY 2026, calendar year):
 *   Jan 05  capital 10,000   Dr Bank 1010 / Cr Share Capital 3000
 *   Feb 10  cash sale 1,500  Dr Bank 1010 / Cr Sales Revenue 4000
 *   Feb 20  rent 400         Dr Rent 6100 / Cr Bank 1010
 * → net profit 1,100; bank 11,100; assets 11,100 == liabilities 0 + equity 11,100.
 */
class FinancialReportsTest extends TestCase
{
    use InteractsWithLedger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeFinanceClock();
    }

    /**
     * @return array{0: User, 1: Company}
     */
    private function scenario(): array
    {
        [$user, $company] = $this->financeUser(['reports.financial.view', 'accounts.view']);

        $this->postSimple($company, '2026-01-05', '1010', '3000', '10000.00', 'Owner capital');
        $this->postSimple($company, '2026-02-10', '1010', '4000', '1500.00', 'Cash sale');
        $this->postSimple($company, '2026-02-20', '6100', '1010', '400.00', 'February rent');

        return [$user, $company];
    }

    public function test_trial_balance_debits_equal_credits(): void
    {
        [$user, $company] = $this->scenario();

        $response = $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/trial-balance?as_of=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.totals.debit_total', '11900.0000')
            ->assertJsonPath('data.totals.credit_total', '11900.0000')
            ->assertJsonPath('data.totals.balanced', true);

        $bank = collect($response->json('data.rows'))->firstWhere('code', '1010');
        $this->assertSame(['11500.0000', '400.0000', '11100.0000'], [$bank['debit_total'], $bank['credit_total'], $bank['balance']]);
        $capital = collect($response->json('data.rows'))->firstWhere('code', '3000');
        $this->assertSame('10000.0000', $capital['balance']);
    }

    public function test_trial_balance_as_of_excludes_later_postings_and_drafts(): void
    {
        [$user, $company] = $this->scenario();
        $this->actAs($user, $company);

        $this->getJson('/api/v1/finance/reports/trial-balance?as_of=2026-01-31')
            ->assertOk()
            ->assertJsonCount(2, 'data.rows')
            ->assertJsonPath('data.totals.debit_total', '10000.0000');
    }

    public function test_profit_and_loss_for_the_known_scenario(): void
    {
        [$user, $company] = $this->scenario();

        $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/profit-and-loss?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.revenue.total', '1500.0000')
            ->assertJsonPath('data.revenue.groups.0.subtype', 'sales_revenue')
            ->assertJsonPath('data.cost_of_sales.total', '0.0000')
            ->assertJsonPath('data.gross_profit', '1500.0000')
            ->assertJsonPath('data.expenses.total', '400.0000')
            ->assertJsonPath('data.expenses.groups.0.subtype', 'operating_expense')
            ->assertJsonPath('data.expenses.groups.0.accounts.0.code', '6100')
            ->assertJsonPath('data.net_profit', '1100.0000');
    }

    public function test_gross_profit_subtracts_cost_of_sales(): void
    {
        [$user, $company] = $this->scenario();
        $this->postSimple($company, '2026-02-11', '5000', '1200', '600.00', 'COGS');

        $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/profit-and-loss?from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.gross_profit', '900.0000')
            ->assertJsonPath('data.net_profit', '500.0000');
    }

    public function test_profit_and_loss_comparison_uses_the_previous_period_of_equal_length(): void
    {
        [$user, $company] = $this->scenario();

        $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/profit-and-loss?from=2026-03-01&to=2026-03-31&compare=true')
            ->assertOk()
            ->assertJsonPath('data.net_profit', '0.0000')
            ->assertJsonPath('comparison.from', '2026-01-29')
            ->assertJsonPath('comparison.to', '2026-02-28')
            ->assertJsonPath('comparison.net_profit', '1100.0000');
    }

    public function test_balance_sheet_balances_with_current_year_earnings(): void
    {
        [$user, $company] = $this->scenario();

        $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/balance-sheet?as_of=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.assets.total', '11100.0000')
            ->assertJsonPath('data.liabilities.total', '0.0000')
            ->assertJsonPath('data.equity.total', '10000.0000')
            ->assertJsonPath('data.equity.current_year_earnings', '1100.0000')
            ->assertJsonPath('data.equity.total_equity', '11100.0000')
            ->assertJsonPath('data.total_liabilities_and_equity', '11100.0000')
            ->assertJsonPath('data.check.balanced', true);
    }

    public function test_cash_flow_opening_and_closing_match_bank_balances(): void
    {
        [$user, $company] = $this->scenario();
        $this->actAs($user, $company);

        $response = $this->getJson('/api/v1/finance/reports/cash-flow?from=2026-02-01&to=2026-02-28')
            ->assertOk()
            ->assertJsonPath('data.opening_cash', '10000.0000')
            ->assertJsonPath('data.closing_cash', '11100.0000')
            ->assertJsonPath('data.operating.inflows', '1500.0000')
            ->assertJsonPath('data.operating.outflows', '400.0000')
            ->assertJsonPath('data.operating.net', '1100.0000')
            ->assertJsonPath('data.financing.net', '0.0000')
            ->assertJsonPath('data.net_change', '1100.0000')
            ->assertJsonPath('data.reconciles', true);

        $balances = $this->getJson('/api/v1/finance/reports/account-balances?as_of=2026-02-28')->assertOk();
        $this->assertSame($response->json('data.closing_cash'), $balances->json('data.totals.cash_and_bank'));

        $balancesJan = $this->getJson('/api/v1/finance/reports/account-balances?as_of=2026-01-31')->assertOk();
        $this->assertSame($response->json('data.opening_cash'), $balancesJan->json('data.totals.cash_and_bank'));

        $this->getJson('/api/v1/finance/reports/cash-flow?from=2026-01-01&to=2026-01-31')
            ->assertJsonPath('data.financing.net', '10000.0000')
            ->assertJsonPath('data.financing.lines.0.code', '3000');
    }

    public function test_general_ledger_shows_opening_and_running_balance(): void
    {
        [$user, $company] = $this->scenario();
        $bank = $this->account($company, '1010');

        $this->actAs($user, $company)
            ->getJson("/api/v1/finance/reports/general-ledger?account_id={$bank->id}&from=2026-02-01&to=2026-02-28")
            ->assertOk()
            ->assertJsonPath('data.opening_balance', '10000.0000')
            ->assertJsonPath('data.closing_balance', '11100.0000')
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.lines.0.running_balance', '11500.0000')
            ->assertJsonPath('data.lines.1.running_balance', '11100.0000')
            ->assertJsonPath('meta.pagination.total', 2);

        // Second page still carries the correct running balance.
        $this->getJson("/api/v1/accounts/{$bank->id}/ledger?per_page=1&page=2&from=2026-02-01")
            ->assertOk()
            ->assertJsonPath('data.lines.0.running_balance', '11100.0000');
    }

    public function test_reversed_entries_net_out_of_reports(): void
    {
        [$user, $company] = $this->scenario();
        $wrong = $this->postSimple($company, '2026-03-01', '6400', '1010', '250.00');
        app(LedgerPoster::class)->reverse($wrong, '2026-03-02');

        $this->actAs($user, $company)
            ->getJson('/api/v1/finance/reports/profit-and-loss?from=2026-01-01&to=2026-12-31')
            ->assertJsonPath('data.net_profit', '1100.0000');
    }

    public function test_reports_require_permission_and_never_show_other_companies(): void
    {
        [, $company] = $this->scenario();
        [$stranger, $otherCompany] = $this->financeUser(['reports.financial.view']);
        [$noPerm] = $this->financeUser(['accounts.view'], $company);

        $this->actAs($noPerm, $company)->getJson('/api/v1/finance/reports/trial-balance')->assertForbidden();

        $this->actAs($stranger, $otherCompany)
            ->getJson('/api/v1/finance/reports/trial-balance?as_of=2026-12-31')
            ->assertOk()
            ->assertJsonCount(0, 'data.rows');

        $foreignBank = $this->account($company, '1010');
        $this->getJson("/api/v1/finance/reports/general-ledger?account_id={$foreignBank->id}")->assertUnprocessable();
    }
}
