<?php

use App\Http\Controllers\Api\V1\Documents\DocumentController;
use App\Http\Controllers\Api\V1\Documents\DocumentFolderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document management
|--------------------------------------------------------------------------
| Loaded inside auth:sanctum + active + tenant. Files live on the private
| `local` disk and are only ever streamed through these routes.
|
| The signed share link stays INSIDE the authenticated group on purpose: the
| signature limits the link's lifetime, the session + policy still decide who
| may open it. There is no anonymous/public document URL.
*/

Route::apiResource('document-folders', DocumentFolderController::class)->parameters(['document-folders' => 'folder']);

Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
Route::get('documents/{document}/shared-download', [DocumentController::class, 'sharedDownload'])
    ->middleware('signed')->name('documents.shared-download');
Route::post('documents/{document}/share-link', [DocumentController::class, 'shareLink'])->name('documents.share-link');
Route::get('documents/{document}/versions', [DocumentController::class, 'versions'])->name('documents.versions.index');
Route::post('documents/{document}/versions', [DocumentController::class, 'storeVersion'])
    ->middleware('permission:documents.manage')->name('documents.versions.store');
Route::apiResource('documents', DocumentController::class);
