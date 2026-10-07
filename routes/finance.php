<?php

use App\Http\Controllers\Finance\AccountController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,finance,principal'])->group(function () {
    Route::get('finance/accounts', [AccountController::class, 'index'])->name('finance.accounts.index');
});

Route::middleware(['auth', 'role:admin,finance'])->group(function () {
    Route::post('finance/accounts', [AccountController::class, 'store'])->name('finance.accounts.store');
    Route::put('finance/accounts/{account}', [AccountController::class, 'update'])
        ->missing(fn () => to_route('finance.accounts.index'))
        ->name('finance.accounts.update');
    Route::delete('finance/accounts/{account}', [AccountController::class, 'destroy'])
        ->missing(fn () => to_route('finance.accounts.index'))
        ->name('finance.accounts.destroy');
});
