<?php

namespace App\Actions\Finance;

use App\Enums\Finance\TaxRateType;
use App\Models\Company;
use App\Models\Finance\Account;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\TaxRate;
use App\Services\Finance\ChartOfAccountsTemplate;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the standard chart of accounts, the current financial year with its
 * monthly periods and the default tax rates for a company. Idempotent: rows
 * are matched by code / date range, so it is safe to re-run on existing
 * companies (POST /accounts/seed-defaults) and from CompanyProvisioned.
 */
class SeedCompanyFinanceDefaultsAction
{
    public function __construct(
        private readonly GeneratePeriodsAction $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{accounts: int, financial_years: int, tax_rates: int}
     */
    public function handle(Company $company): array
    {
        return DB::transaction(function () use ($company): array {
            $created = [
                'accounts' => $this->seedAccounts($company),
                'financial_years' => $this->seedFinancialYear($company),
                'tax_rates' => $this->seedTaxRates($company),
            ];

            if (array_sum($created) > 0) {
                $this->audit->log('finance.defaults_seeded', $company, companyId: $company->id, context: $created);
            }

            return $created;
        });
    }

    private function seedAccounts(Company $company): int
    {
        $existing = Account::forCompany($company->id)->pluck('code')->all();
        $count = 0;

        // Bulk seed without per-row audit noise; one summary audit row is written instead.
        Account::withoutEvents(function () use ($company, $existing, &$count): void {
            foreach (ChartOfAccountsTemplate::accounts() as $row) {
                if (in_array($row['code'], $existing, true)) {
                    continue;
                }

                Account::query()->create([
                    'company_id' => $company->id,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'subtype' => $row['subtype']->value,
                    'is_cash' => $row['is_cash'] ?? false,
                    'is_bank' => $row['is_bank'] ?? false,
                    'is_system' => true,
                    'is_active' => true,
                    'currency' => $company->currency ?: 'USD',
                ]);
                $count++;
            }
        });

        return $count;
    }

    private function seedFinancialYear(Company $company): int
    {
        $today = now()->toDateString();

        $covered = FinancialYear::forCompany($company->id)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->exists();

        if ($covered) {
            return 0;
        }

        $this->periods->handle($company);

        return 1;
    }

    private function seedTaxRates(Company $company): int
    {
        $existing = TaxRate::forCompany($company->id)->pluck('code')->all();
        $accountIds = Account::forCompany($company->id)->pluck('id', 'code');
        $count = 0;

        foreach (ChartOfAccountsTemplate::taxRates() as $row) {
            if (in_array($row['code'], $existing, true)) {
                continue;
            }

            TaxRate::query()->create([
                'company_id' => $company->id,
                'code' => $row['code'],
                'name' => $row['name'],
                'rate' => $row['rate'],
                'type' => TaxRateType::from($row['type']),
                'account_id' => $row['account_code'] === null ? null : $accountIds->get($row['account_code']),
                'is_compound' => false,
                'is_active' => true,
            ]);
            $count++;
        }

        return $count;
    }
}
