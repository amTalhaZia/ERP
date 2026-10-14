<?php

namespace App\Providers;

use App\Models\Finance\Bill;
use App\Models\Finance\ExpenseClaim;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Policies\Finance\BillPolicy;
use App\Policies\Finance\ExpenseClaimPolicy;
use App\Policies\Finance\InvoicePolicy;
use App\Policies\Finance\PaymentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Accounts receivable / payable wiring (invoices, bills, payments, expense
 * claims). Policies live in App\Policies\Finance, so they are registered here.
 */
class ReceivablesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const POLICIES = [
        Invoice::class => InvoicePolicy::class,
        Bill::class => BillPolicy::class,
        Payment::class => PaymentPolicy::class,
        ExpenseClaim::class => ExpenseClaimPolicy::class,
    ];

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Gate::define('receivables.statements.view', fn (User $user) => $user->hasAnyPermission(['reports.financial.view', 'invoices.view']));
    }
}
