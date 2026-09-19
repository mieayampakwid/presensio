<?php

use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('forgot-password', [PasswordResetController::class, 'create'])
    ->name('password.request');

Route::post('forgot-password', [PasswordResetController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('password.email');

Route::get('reset-password/{token}', [PasswordResetController::class, 'edit'])
    ->name('password.reset');

Route::post('reset-password', [PasswordResetController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('password.update');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/users.php';
require __DIR__.'/classes.php';
require __DIR__.'/teachers.php';
require __DIR__.'/guardians.php';
require __DIR__.'/students.php';
require __DIR__.'/rfid-cards.php';
require __DIR__.'/attendance.php';
require __DIR__.'/non-school-days.php';
