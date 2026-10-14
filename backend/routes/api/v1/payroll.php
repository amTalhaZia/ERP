<?php

use App\Http\Controllers\Api\V1\Payroll\EmployeeSalaryController;
use App\Http\Controllers\Api\V1\Payroll\MyPayslipController;
use App\Http\Controllers\Api\V1\Payroll\PayComponentController;
use App\Http\Controllers\Api\V1\Payroll\PayrollReportController;
use App\Http\Controllers\Api\V1\Payroll\PayrollRunController;
use App\Http\Controllers\Api\V1\Payroll\PayslipController;
use App\Http\Controllers\Api\V1\Payroll\SalaryStructureController;
use App\Http\Controllers\Api\V1\Payroll\TaxTableController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Payroll
|--------------------------------------------------------------------------
| Already inside auth:sanctum + active + tenant. Route names: api.v1.payroll.*
| Policies do the fine-grained checks; `permission:` guards sensitive verbs.
*/
Route::name('payroll.')->group(function (): void {

    // -- Configuration ---------------------------------------------------------
    Route::apiResource('pay-components', PayComponentController::class);
    Route::apiResource('tax-tables', TaxTableController::class);
    Route::apiResource('salary-structures', SalaryStructureController::class);

    Route::get('employees/{employee}/salaries', [EmployeeSalaryController::class, 'index'])->name('employees.salaries.index');
    Route::post('employees/{employee}/salaries', [EmployeeSalaryController::class, 'store'])
        ->middleware('permission:payroll.manage')->name('employees.salaries.store');

    // -- Runs ------------------------------------------------------------------
    Route::middleware('permission:payroll.process')->group(function (): void {
        Route::post('payroll-runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
        Route::delete('payroll-runs/{payroll_run}', [PayrollRunController::class, 'destroy'])->name('payroll-runs.destroy');
        Route::post('payroll-runs/{payroll_run}/calculate', [PayrollRunController::class, 'calculate'])->name('payroll-runs.calculate');
        Route::post('payroll-runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->name('payroll-runs.approve');
        Route::post('payroll-runs/{payroll_run}/finalize', [PayrollRunController::class, 'finalize'])->name('payroll-runs.finalize');
        Route::post('payroll-runs/{payroll_run}/pay', [PayrollRunController::class, 'pay'])->name('payroll-runs.pay');
        Route::post('payroll-runs/{payroll_run}/cancel', [PayrollRunController::class, 'cancel'])->name('payroll-runs.cancel');
    });

    Route::middleware('permission:payroll.view')->group(function (): void {
        Route::get('payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->name('payroll-runs.show');
        Route::get('payroll-runs/{payroll_run}/payslips', [PayrollRunController::class, 'payslips'])->name('payroll-runs.payslips');
        Route::get('payroll-runs/{payroll_run}/export', [PayrollRunController::class, 'export'])->name('payroll-runs.export');

        Route::get('payroll/reports/summary', [PayrollReportController::class, 'summary'])->name('reports.summary');
        Route::get('payroll/reports/components', [PayrollReportController::class, 'components'])->name('reports.components');
    });

    // -- Payslips (payroll.view, or the owner via payslips.view.own — policy) ---
    Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
    Route::get('payslips/{payslip}/pdf', [PayslipController::class, 'pdf'])->name('payslips.pdf');

    // -- Self-service ------------------------------------------------------------
    Route::middleware('permission:payslips.view.own')->group(function (): void {
        Route::get('my-payslips', [MyPayslipController::class, 'index'])->name('my-payslips.index');
        Route::get('my-payslips/{payslip}', [MyPayslipController::class, 'show'])->whereNumber('payslip')->name('my-payslips.show');
        Route::get('my-payslips/{payslip}/pdf', [MyPayslipController::class, 'pdf'])->whereNumber('payslip')->name('my-payslips.pdf');
    });
});
