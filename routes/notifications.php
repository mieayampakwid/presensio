<?php

use App\Http\Controllers\Admin\NotificationDeliveryController;
use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('notification-deliveries', [NotificationDeliveryController::class, 'index'])
        ->middleware('role:admin,principal')
        ->name('notification-deliveries.index');
});
