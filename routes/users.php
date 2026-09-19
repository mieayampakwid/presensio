<?php

use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->missing(fn () => to_route('users.index'))
        ->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->missing(fn () => to_route('users.index'))
        ->name('users.update');
    Route::put('users/{user}/password', [UserController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->missing(fn () => to_route('users.index'))
        ->name('users.password.update');
});
