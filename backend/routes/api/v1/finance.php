<?php

use App\Http\Controllers\Api\V1\Finance\AccountController;
use App\Http\Controllers\Api\V1\Finance\BankAccountController;
use App\Http\Controllers\Api\V1\Finance\FinancialPeriodController;
use App\Http\Controllers\Api\V1\Finance\FinancialYearController;
use App\Http\Controllers\Api\V1\Finance\JournalEntryController;
use App\Http\Controllers\Api\V1\Finance\ReportController;
use App\Http\Controllers\Api\V1\Finance\TaxRateController;
use Illuminate\Support\Facades\Route;

// Chart of accounts
Route::get('accounts/tree', [AccountController::class, 'tree'])->name('accounts.tree');
Route::post('accounts/seed-defaults', [AccountController::class, 'seedDefaults'])
    ->middleware('permission:accounts.manage')->name('accounts.seed-defaults');
Route::get('accounts/{account}/ledger', [AccountController::class, 'ledger'])->name('accounts.ledger');
Route::apiResource('accounts', AccountController::class);

// Financial years & periods
Route::apiResource('financial-years', FinancialYearController::class)->only(['index', 'store', 'show']);
Route::post('financial-periods/{financialPeriod}/close', [FinancialPeriodController::class, 'close'])
    ->middleware('permission:periods.manage')->name('financial-periods.close');
Route::post('financial-periods/{financialPeriod}/reopen', [FinancialPeriodController::class, 'reopen'])
    ->middleware('permission:periods.manage')->name('financial-periods.reopen');
Route::apiResource('financial-periods', FinancialPeriodController::class)->only(['index', 'show']);

// Tax rates & bank accounts
Route::apiResource('tax-rates', TaxRateController::class);
Route::apiResource('bank-accounts', BankAccountController::class);

// General ledger
Route::post('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
    ->middleware('permission:journals.post')->name('journal-entries.post');
Route::post('journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])
    ->middleware('permission:journals.post')->name('journal-entries.reverse');
Route::apiResource('journal-entries', JournalEntryController::class);

// Financial reports
Route::prefix('finance/reports')->name('finance.reports.')->middleware('permission:reports.financial.view')->group(function (): void {
    Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
    Route::get('profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('profit-and-loss');
    Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
    Route::get('cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
    Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
    Route::get('account-balances', [ReportController::class, 'accountBalances'])->name('account-balances');
});
