<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Finance\Invoice;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\Demo\PayrollDemoSeeder;
use Database\Seeders\Demo\SmallFinanceDemoSeeder;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Adds HR activity (leave, attendance), accounting (invoices, bills, payments,
 * journals) and payroll (three runs)
 * to the existing small demo company without touching anything already there.
 * Runs once: it stops if the company already has invoices.
 *
 *   php artisan db:seed --class=SmallDemoExtrasSeeder
 */
class SmallDemoExtrasSeeder extends Seeder
{
    public function run(TenantContext $tenant): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SmallDemoExtrasSeeder must never run in production.');
        }

        $company = Company::query()->where('email', 'info@northwind.test')->where('is_demo', true)->firstOrFail();

        $tenant->set($company, null);
        if (Invoice::query()->exists()) {
            $this->command?->warn('Invoices already exist — extras skipped.');
            $tenant->clear();

            return;
        }

        $this->callWith(SmallHrDemoSeeder::class, ['company' => $company]);
        $this->callWith(SmallFinanceDemoSeeder::class, ['company' => $company]);
        $this->callWith(PayrollDemoSeeder::class, ['company' => $company]);
        $tenant->clear();
    }
}
