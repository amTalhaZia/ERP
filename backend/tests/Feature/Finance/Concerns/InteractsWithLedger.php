<?php

namespace Tests\Feature\Finance\Concerns;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\User;
use App\Services\Finance\LedgerPoster;
use Illuminate\Support\Carbon;

/**
 * Arrange helpers for finance tests: a company with the standard chart, FY 2026
 * periods and tax rates; postings go through the public LedgerPoster API.
 */
trait InteractsWithLedger
{
    /**
     * actingAsIn() that also drops the cached Sanctum user, so a test can switch
     * between users across requests.
     */
    protected function actAs(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }

    protected function freezeFinanceClock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array{0: User, 1: Company}
     */
    protected function financeUser(array $permissions, ?Company $company = null): array
    {
        [$user, $company] = $this->userWith($permissions, $company);

        if (! Account::forCompany($company->id)->exists()) {
            app(SeedCompanyFinanceDefaultsAction::class)->handle($company);
        }

        return [$user, $company];
    }

    protected function seededCompany(): Company
    {
        $company = Company::factory()->create();
        app(SeedCompanyFinanceDefaultsAction::class)->handle($company);

        return $company;
    }

    protected function account(Company $company, string $code): Account
    {
        return Account::forCompany($company->id)->where('code', $code)->firstOrFail();
    }

    /**
     * Post a simple two-line entry: debit $debitCode / credit $creditCode.
     */
    protected function postSimple(Company $company, string $date, string $debitCode, string $creditCode, string $amount, string $memo = 'Test posting'): JournalEntry
    {
        return app(LedgerPoster::class)->post([
            'company_id' => $company->id,
            'entry_date' => $date,
            'memo' => $memo,
            'reference' => 'T-'.$debitCode.'-'.$creditCode,
            'source_type' => 'manual',
            'lines' => [
                ['account_code' => $debitCode, 'debit' => $amount, 'credit' => '0'],
                ['account_code' => $creditCode, 'debit' => '0', 'credit' => $amount],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function draftPayload(Company $company, string $date = '2026-03-15', string $debit = '250.00', string $credit = '250.00'): array
    {
        return [
            'entry_date' => $date,
            'reference' => 'MAN-1',
            'memo' => 'Office supplies paid from bank',
            'lines' => [
                ['account_id' => $this->account($company, '6300')->id, 'debit' => $debit, 'credit' => '0', 'description' => 'Paper'],
                ['account_id' => $this->account($company, '1010')->id, 'debit' => '0', 'credit' => $credit],
            ],
        ];
    }
}
