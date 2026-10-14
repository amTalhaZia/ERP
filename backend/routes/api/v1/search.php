<?php

use App\Http\Controllers\Api\V1\Search\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Global search
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Any member may search; each
| result type is gated by its module's view permission.
*/

Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');
