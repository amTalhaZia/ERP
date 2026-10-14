<?php

use App\Http\Controllers\Api\V1\Access\AuditLogController;
use App\Http\Controllers\Api\V1\Access\CompanyUserController;
use App\Http\Controllers\Api\V1\Access\PermissionController;
use App\Http\Controllers\Api\V1\Access\RoleController;
use Illuminate\Support\Facades\Route;

// Company users (memberships of the current company)
Route::get('users', [CompanyUserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
Route::post('users', [CompanyUserController::class, 'store'])->middleware('permission:users.manage')->name('users.store');
Route::get('users/{user}', [CompanyUserController::class, 'show'])->middleware('permission:users.view')->whereNumber('user')->name('users.show');
Route::match(['put', 'patch'], 'users/{user}', [CompanyUserController::class, 'update'])->middleware('permission:users.manage')->whereNumber('user')->name('users.update');
Route::delete('users/{user}', [CompanyUserController::class, 'destroy'])->middleware('permission:users.manage')->whereNumber('user')->name('users.destroy');
Route::post('users/{user}/resend-invite', [CompanyUserController::class, 'resendInvite'])->middleware(['permission:users.manage', 'throttle:auth'])->whereNumber('user')->name('users.resend-invite');

// Permission catalogue & roles
Route::get('permissions', [PermissionController::class, 'index'])->middleware('permission:roles.view')->name('permissions.index');
Route::apiResource('roles', RoleController::class)
    ->whereNumber('role')
    ->middlewareFor(['index', 'show'], 'permission:roles.view')
    ->middlewareFor(['store', 'update', 'destroy'], 'permission:roles.manage');

// Audit logs (read-only, append-only table)
Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view')->name('audit-logs.index');
Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->middleware('permission:audit.view')->whereNumber('auditLog')->name('audit-logs.show');
