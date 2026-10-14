<?php

use App\Http\Controllers\Api\V1\HR\AttendanceController;
use App\Http\Controllers\Api\V1\HR\DesignationController;
use App\Http\Controllers\Api\V1\HR\EmployeeController;
use App\Http\Controllers\Api\V1\HR\EmployeeDocumentController;
use App\Http\Controllers\Api\V1\HR\EmploymentContractController;
use App\Http\Controllers\Api\V1\HR\HolidayController;
use App\Http\Controllers\Api\V1\HR\HrReportController;
use App\Http\Controllers\Api\V1\HR\LeaveBalanceController;
use App\Http\Controllers\Api\V1\HR\LeaveRequestController;
use App\Http\Controllers\Api\V1\HR\LeaveTypeController;
use App\Http\Controllers\Api\V1\HR\PerformanceReviewController;
use App\Http\Controllers\Api\V1\HR\ShiftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HR & Employee Management
|--------------------------------------------------------------------------
| Already inside auth:sanctum + active + tenant. Route names: api.v1.hr.*
| Policies do the fine-grained checks; `permission:` guards sensitive verbs.
*/
Route::name('hr.')->group(function (): void {

    // -- Designations -------------------------------------------------------
    Route::apiResource('designations', DesignationController::class);

    // -- Employees ----------------------------------------------------------
    Route::get('employees/me', [EmployeeController::class, 'me'])->name('employees.me');
    Route::get('employees/directory', [EmployeeController::class, 'directory'])->name('employees.directory');
    Route::get('employees/org-chart', [EmployeeController::class, 'orgChart'])->name('employees.org-chart');
    Route::post('employees/{employee}/terminate', [EmployeeController::class, 'terminate'])
        ->middleware('permission:employees.update')->name('employees.terminate');
    Route::post('employees/{employee}/reactivate', [EmployeeController::class, 'reactivate'])
        ->middleware('permission:employees.update')->name('employees.reactivate');
    Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto'])
        ->middleware('permission:employees.update')->name('employees.photo');
    Route::get('employees/{employee}/shifts', [ShiftController::class, 'assignments'])->name('employees.shifts.index');
    Route::post('employees/{employee}/shifts', [ShiftController::class, 'assign'])
        ->middleware('permission:shifts.manage')->name('employees.shifts.store');
    Route::get('employees/{employee}/documents', [EmployeeDocumentController::class, 'index'])->name('employees.documents.index');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->middleware('permission:employees.update')->name('employees.documents.store');
    Route::apiResource('employees', EmployeeController::class);

    // -- Employee documents -------------------------------------------------
    Route::get('employee-documents/{employee_document}/download', [EmployeeDocumentController::class, 'download'])->name('employee-documents.download');
    Route::delete('employee-documents/{employee_document}', [EmployeeDocumentController::class, 'destroy'])
        ->middleware('permission:employees.update')->name('employee-documents.destroy');

    // -- Contracts ----------------------------------------------------------
    Route::get('employment-contracts/{employment_contract}/document', [EmploymentContractController::class, 'document'])->name('employment-contracts.document');
    Route::apiResource('employment-contracts', EmploymentContractController::class);

    // -- Shifts & holidays --------------------------------------------------
    Route::apiResource('shifts', ShiftController::class);
    Route::apiResource('holidays', HolidayController::class);

    // -- Attendance ---------------------------------------------------------
    Route::post('attendance/bulk', [AttendanceController::class, 'bulk'])
        ->middleware('permission:attendance.manage')->name('attendance.bulk');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::get('attendance/summary', [AttendanceController::class, 'summary'])
        ->middleware('permission:attendance.view')->name('attendance.summary');
    Route::apiResource('attendance', AttendanceController::class)->parameters(['attendance' => 'attendance']);

    // -- Leave --------------------------------------------------------------
    Route::apiResource('leave-types', LeaveTypeController::class);

    Route::get('leave-balances/mine', [LeaveBalanceController::class, 'mine'])->name('leave-balances.mine');
    Route::post('leave-balances/generate', [LeaveBalanceController::class, 'generate'])
        ->middleware('permission:leave.manage')->name('leave-balances.generate');
    Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::put('leave-balances/{leave_balance}', [LeaveBalanceController::class, 'adjust'])
        ->middleware('permission:leave.manage')->name('leave-balances.adjust');

    Route::post('leave-requests/{leave_request}/approve', [LeaveRequestController::class, 'approve'])
        ->middleware('permission:leave.approve')->name('leave-requests.approve');
    Route::post('leave-requests/{leave_request}/reject', [LeaveRequestController::class, 'reject'])
        ->middleware('permission:leave.approve')->name('leave-requests.reject');
    Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->name('leave-requests.cancel');
    Route::get('leave-requests/{leave_request}/attachment', [LeaveRequestController::class, 'attachment'])->name('leave-requests.attachment');
    Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'store', 'show']);

    // -- Performance --------------------------------------------------------
    Route::post('performance-reviews/{performance_review}/submit', [PerformanceReviewController::class, 'submit'])->name('performance-reviews.submit');
    Route::post('performance-reviews/{performance_review}/acknowledge', [PerformanceReviewController::class, 'acknowledge'])->name('performance-reviews.acknowledge');
    Route::apiResource('performance-reviews', PerformanceReviewController::class);

    // -- Reports ------------------------------------------------------------
    Route::prefix('hr/reports')->name('reports.')->middleware('permission:employees.view')->group(function (): void {
        Route::get('headcount', [HrReportController::class, 'headcount'])->name('headcount');
        Route::get('attendance', [HrReportController::class, 'attendance'])->name('attendance');
        Route::get('leave', [HrReportController::class, 'leave'])->name('leave');
    });
});
