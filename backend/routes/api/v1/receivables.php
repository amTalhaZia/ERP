<?php

use App\Http\Controllers\Api\V1\Finance\BillController;
use App\Http\Controllers\Api\V1\Finance\ExpenseClaimController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use App\Http\Controllers\Api\V1\Finance\ReceivablesReportController;
use Illuminate\Support\Facades\Route;

// Accounts receivable: customer invoices
Route::post('invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])
    ->middleware('permission:invoices.approve')->name('invoices.approve');
Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])
    ->middleware('permission:invoices.approve')->name('invoices.void');
Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
Route::apiResource('invoices', InvoiceController::class);

// Accounts payable: supplier bills
Route::post('bills/{bill}/submit', [BillController::class, 'submit'])->name('bills.submit');
Route::post('bills/{bill}/approve', [BillController::class, 'approve'])
    ->middleware('permission:bills.approve')->name('bills.approve');
Route::post('bills/{bill}/void', [BillController::class, 'void'])
    ->middleware('permission:bills.approve')->name('bills.void');
Route::apiResource('bills', BillController::class);

// Receipts & payments
Route::post('payments/{payment}/void', [PaymentController::class, 'void'])
    ->middleware('permission:payments.create')->name('payments.void');
Route::apiResource('payments', PaymentController::class)->only(['index', 'store', 'show']);

// Expense claims
Route::post('expense-claims/{expenseClaim}/submit', [ExpenseClaimController::class, 'submit'])->name('expense-claims.submit');
Route::post('expense-claims/{expenseClaim}/approve', [ExpenseClaimController::class, 'approve'])
    ->middleware('permission:expenses.approve')->name('expense-claims.approve');
Route::post('expense-claims/{expenseClaim}/reject', [ExpenseClaimController::class, 'reject'])
    ->middleware('permission:expenses.approve')->name('expense-claims.reject');
Route::scopeBindings()->group(function (): void {
    Route::post('expense-claims/{expenseClaim}/lines/{line}/receipt', [ExpenseClaimController::class, 'uploadReceipt'])
        ->name('expense-claims.lines.receipt.store');
    Route::get('expense-claims/{expenseClaim}/lines/{line}/receipt', [ExpenseClaimController::class, 'downloadReceipt'])
        ->name('expense-claims.lines.receipt.show');
});
Route::apiResource('expense-claims', ExpenseClaimController::class)->parameters(['expense-claims' => 'expenseClaim']);

// AR / AP reports
Route::prefix('finance/reports')->name('finance.reports.')->middleware('permission:reports.financial.view')->group(function (): void {
    Route::get('ar-aging', [ReceivablesReportController::class, 'arAging'])->name('ar-aging');
    Route::get('ap-aging', [ReceivablesReportController::class, 'apAging'])->name('ap-aging');
    Route::get('receivables-summary', [ReceivablesReportController::class, 'summary'])->name('receivables-summary');
});
Route::get('finance/customer-statements/{customer}', [ReceivablesReportController::class, 'customerStatement'])
    ->whereNumber('customer')->name('finance.customer-statements.show');
