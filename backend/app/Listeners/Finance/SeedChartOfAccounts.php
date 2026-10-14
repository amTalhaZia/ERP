<?php

namespace App\Listeners\Finance;

use App\Actions\Finance\SeedCompanyFinanceDefaultsAction;
use App\Events\CompanyProvisioned;

/**
 * Every new company gets the standard chart of accounts, the current financial
 * year (12 monthly periods from fiscal_year_start_month) and default tax rates.
 * Runs synchronously inside the provisioning transaction (auto-discovered).
 */
class SeedChartOfAccounts
{
    public function __construct(private readonly SeedCompanyFinanceDefaultsAction $seed) {}

    public function handle(CompanyProvisioned $event): void
    {
        $this->seed->handle($event->company);
    }
}
