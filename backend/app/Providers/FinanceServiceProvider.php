<?php

namespace App\Providers;

use App\Models\Finance\Account;
use App\Models\Finance\BankAccount;
use App\Models\Finance\FinancialPeriod;
use App\Models\Finance\FinancialYear;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\TaxRate;
use App\Models\User;
use App\Policies\Finance\AccountPolicy;
use App\Policies\Finance\BankAccountPolicy;
use App\Policies\Finance\FinancialPeriodPolicy;
use App\Policies\Finance\FinancialYearPolicy;
use App\Policies\Finance\JournalEntryPolicy;
use App\Policies\Finance\TaxRatePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Finance module wiring. Policies live in App\Policies\Finance (one namespace
 * level deeper than Laravel's auto-discovery expects), so they are registered
 * explicitly here. Event listeners are auto-discovered from app/Listeners.
 */
class FinanceServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Account::class => AccountPolicy::class,
        BankAccount::class => BankAccountPolicy::class,
        FinancialPeriod::class => FinancialPeriodPolicy::class,
        FinancialYear::class => FinancialYearPolicy::class,
        JournalEntry::class => JournalEntryPolicy::class,
        TaxRate::class => TaxRatePolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::define('finance.reports.view', fn (User $user) => $user->hasPermission('reports.financial.view'));
    }
}
