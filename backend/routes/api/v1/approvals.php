<?php

use App\Http\Controllers\Api\V1\Approvals\ApprovalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Approvals inbox (read-only aggregation)
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Items are filtered per
| type by each module's `x.approve` permission inside ApprovalInbox.
*/

Route::middleware('permission:approvals.view')->group(function (): void {
    Route::get('approvals/counts', [ApprovalController::class, 'counts'])->name('approvals.counts');
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
});
