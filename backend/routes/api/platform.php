<?php

use App\Http\Controllers\Api\V1\Platform\PlatformAuditLogController;
use App\Http\Controllers\Api\V1\Platform\PlatformCompanyController;
use App\Http\Controllers\Api\V1\Platform\PlatformUserController;
use App\Http\Controllers\Api\V1\Platform\SystemController;
use Illuminate\Support\Facades\Route;

/*
| Already inside: prefix `v1/platform`, name `api.v1.platform.`, middleware
| `auth:sanctum` + `active` + `platform` (404 for non-platform users).
| There is NO tenant context here; platform staff have no company.
*/

// Companies
Route::middleware('permission:platform.companies.manage')->group(function (): void {
    Route::apiResource('companies', PlatformCompanyController::class)->whereNumber('company');
    Route::post('companies/{company}/activate', [PlatformCompanyController::class, 'activate'])->whereNumber('company')->name('companies.activate');
    Route::post('companies/{company}/deactivate', [PlatformCompanyController::class, 'deactivate'])->whereNumber('company')->name('companies.deactivate');
    Route::get('companies/{company}/members', [PlatformCompanyController::class, 'members'])->whereNumber('company')->name('companies.members');
});

// Users
Route::middleware('permission:platform.users.manage')->group(function (): void {
    Route::get('users', [PlatformUserController::class, 'index'])->name('users.index');
    Route::get('users/{user}', [PlatformUserController::class, 'show'])->whereNumber('user')->name('users.show');
    Route::match(['put', 'patch'], 'users/{user}', [PlatformUserController::class, 'update'])->whereNumber('user')->name('users.update');
});

// System overview & cross-company audit trail
Route::middleware('permission:platform.system.view')->group(function (): void {
    Route::get('system', [SystemController::class, 'index'])->name('system');
    Route::get('system/usage', [SystemController::class, 'usage'])->name('system.usage');
    Route::get('audit-logs', [PlatformAuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [PlatformAuditLogController::class, 'show'])->whereNumber('auditLog')->name('audit-logs.show');
});
