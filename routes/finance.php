<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\ManualJournalController;
use Illuminate\Support\Facades\Route;

// Read routes: Admin, Finance, Principal
Route::middleware(['auth', 'role:admin,finance,principal'])->group(function () {
    Route::get('finance/accounts', [AccountController::class, 'index'])->name('finance.accounts.index');

    Route::get('finance/journals', [ManualJournalController::class, 'index'])->name('finance.journals.index');
    Route::get('finance/journals/create', [ManualJournalController::class, 'create'])->name('finance.journals.create');
    Route::get('finance/journals/{journal}', [ManualJournalController::class, 'show'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.show');
    Route::get('finance/journals/{journal}/attachment', [ManualJournalController::class, 'attachment'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.attachment');
});

// Write routes: Admin, Finance only
Route::middleware(['auth', 'role:admin,finance'])->group(function () {
    Route::post('finance/accounts', [AccountController::class, 'store'])->name('finance.accounts.store');
    Route::put('finance/accounts/{account}', [AccountController::class, 'update'])
        ->missing(fn () => to_route('finance.accounts.index'))
        ->name('finance.accounts.update');
    Route::delete('finance/accounts/{account}', [AccountController::class, 'destroy'])
        ->missing(fn () => to_route('finance.accounts.index'))
        ->name('finance.accounts.destroy');

    Route::post('finance/journals', [ManualJournalController::class, 'store'])->name('finance.journals.store');
    Route::get('finance/journals/{journal}/edit', [ManualJournalController::class, 'edit'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.edit');
    Route::put('finance/journals/{journal}', [ManualJournalController::class, 'update'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.update');
    Route::delete('finance/journals/{journal}', [ManualJournalController::class, 'destroy'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.destroy');

    Route::post('finance/journals/{journal}/post', [ManualJournalController::class, 'post'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.post');
    Route::post('finance/journals/{journal}/reverse', [ManualJournalController::class, 'reverse'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.reverse');
});
