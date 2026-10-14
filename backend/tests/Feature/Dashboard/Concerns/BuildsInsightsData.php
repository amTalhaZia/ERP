<?php

namespace Tests\Feature\Dashboard\Concerns;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\JournalEntry;
use App\Models\HR\Employee;
use App\Models\User;
use App\Services\Finance\LedgerPoster;
use Illuminate\Support\Carbon;

/**
 * Arrange helpers shared by the Dashboard / Approvals / Reports / Search
 * suites. Runs on the full migration set (no migrateFreshUsing override).
 */
trait BuildsInsightsData
{
    protected function freezeClock(): void
    {
        $this->travelTo(Carbon::parse('2026-10-15 12:00:00'));
    }

    protected function actAs(User $user, Company $company): static
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsIn($user, $company);
    }

    protected function withLedger(Company $company): Company
    {
        if (! Account::forCompany($company->id)->exists()) {
            app(SeedCompanyFinanceDefaultsAction::class)->handle($company);
        }

        return $company;
    }

    protected function postEntry(Company $company, string $date, string $debitCode, string $creditCode, string $amount, ?int $branchId = null): JournalEntry
    {
        $this->withLedger($company);

        return app(LedgerPoster::class)->post([
            'company_id' => $company->id,
            'entry_date' => $date,
            'memo' => 'Test posting',
            'reference' => 'T-'.$debitCode.'-'.$creditCode,
            'source_type' => 'manual',
            'branch_id' => $branchId,
            'lines' => [
                ['account_code' => $debitCode, 'debit' => $amount, 'credit' => '0', 'branch_id' => $branchId],
                ['account_code' => $creditCode, 'debit' => '0', 'credit' => $amount, 'branch_id' => $branchId],
            ],
        ]);
    }

    protected function branch(Company $company, string $name): Branch
    {
        return Branch::factory()->create(['company_id' => $company->id, 'name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function employee(Company $company, array $attributes = []): Employee
    {
        return Employee::factory()->create(['company_id' => $company->id] + $attributes);
    }
}
