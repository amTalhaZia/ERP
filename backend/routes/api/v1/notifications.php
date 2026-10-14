<?php

use App\Http\Controllers\Api\V1\Notifications\NotificationController;
use App\Http\Controllers\Api\V1\Notifications\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

// In-app notifications of the current user (filtered to the active company when stamped)
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
Route::get('notifications/preferences', [NotificationPreferenceController::class, 'show'])->name('notifications.preferences.show');
Route::put('notifications/preferences', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences.update');
Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id')->name('notifications.read');
Route::delete('notifications/{id}', [NotificationController::class, 'destroy'])->whereUuid('id')->name('notifications.destroy');
