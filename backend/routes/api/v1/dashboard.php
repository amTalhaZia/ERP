<?php

use App\Http\Controllers\Api\V1\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Executive dashboard
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Module sections are filtered
| per permission inside DashboardService.
*/

Route::get('dashboard', DashboardController::class)
    ->middleware('permission:dashboard.view')
    ->name('dashboard');
