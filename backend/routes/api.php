<?php

use App\Http\Controllers\Api\V1\Auth\AccountController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Middleware vocabulary:
|   auth:sanctum  — SPA cookie session (or token)
|   active        — refuses deactivated accounts
|   tenant        — resolves & verifies the company context (X-Company-Id)
|   permission:x  — coarse RBAC gate; policies do the fine-grained checks
|   platform      — platform administrators only (404 otherwise)
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {

    // -- Health (public, safe) -------------------------------------------
    Route::get('health', [HealthController::class, 'app'])->name('health');
    Route::get('health/db', [HealthController::class, 'database'])->name('health.db');

    // -- Authentication (guest) ------------------------------------------
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [LoginController::class, 'login'])->middleware(['guest:web', 'throttle:login'])->name('login');
        Route::post('2fa/challenge', [TwoFactorController::class, 'challenge'])->middleware(['guest:web', 'throttle:two-factor'])->name('2fa.challenge');
        Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware(['guest:web', 'throttle:auth'])->name('password.email');
        Route::post('reset-password', [PasswordController::class, 'reset'])->middleware(['guest:web', 'throttle:auth'])->name('password.reset');
        Route::get('email/verify/{id}/{hash}', [AccountController::class, 'verify'])->middleware(['signed', 'throttle:auth'])->name('verification.verify');
    });

    // -- Authenticated -----------------------------------------------------
    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('logout', [LoginController::class, 'logout'])->name('logout');
            Route::get('session', [SessionController::class, 'show'])->middleware('tenant:optional')->name('session');
            Route::post('switch-company', [SessionController::class, 'switchCompany'])->name('switch-company');
            Route::post('password', [PasswordController::class, 'update'])->name('password.update');
            Route::post('confirm-password', [PasswordController::class, 'confirm'])->middleware('throttle:auth')->name('password.confirm');
            Route::patch('preferences', [AccountController::class, 'updatePreferences'])->name('preferences');
            Route::patch('profile', [AccountController::class, 'updateProfile'])->name('profile');
            Route::post('email/verification-notification', [AccountController::class, 'sendVerification'])->middleware('throttle:auth')->name('verification.send');

            Route::prefix('2fa')->name('2fa.')->group(function (): void {
                Route::post('enable', [TwoFactorController::class, 'enable'])->middleware('password.confirm')->name('enable');
                Route::post('confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:two-factor')->name('confirm');
                Route::post('disable', [TwoFactorController::class, 'disable'])->name('disable');
                Route::get('recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->middleware('password.confirm')->name('recovery-codes');
            });
        });

        // -- Tenant-scoped modules ----------------------------------------
        Route::middleware('tenant')->group(function (): void {
            foreach (glob(__DIR__.'/api/v1/*.php') ?: [] as $moduleRoutes) {
                require $moduleRoutes;
            }
        });

        // -- Platform administration ---------------------------------------
        Route::prefix('platform')->name('platform.')->middleware('platform')->group(function (): void {
            if (file_exists(__DIR__.'/api/platform.php')) {
                require __DIR__.'/api/platform.php';
            }
        });
    });
});
