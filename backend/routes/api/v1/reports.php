<?php

use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Reports\ReportExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports & exports
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Each report additionally
| requires its own permission(s) (the `runReport` gate).
*/

Route::get('reports', [ReportController::class, 'index'])
    ->middleware('permission:reports.view')->name('reports.index');
Route::get('reports/{key}', [ReportController::class, 'show'])
    ->middleware('permission:reports.view')->where('key', '[a-z_]+\.[a-z_]+')->name('reports.show');
Route::post('reports/{key}/export', [ReportController::class, 'export'])
    ->middleware(['permission:reports.export', 'throttle:exports'])->where('key', '[a-z_]+\.[a-z_]+')->name('reports.export');

Route::middleware('permission:reports.export')->group(function (): void {
    Route::get('report-exports', [ReportExportController::class, 'index'])->name('report-exports.index');
    Route::get('report-exports/{exportJob}', [ReportExportController::class, 'show'])->whereUuid('exportJob')->name('report-exports.show');
    Route::get('report-exports/{exportJob}/download', [ReportExportController::class, 'download'])->whereUuid('exportJob')->name('report-exports.download');
});
