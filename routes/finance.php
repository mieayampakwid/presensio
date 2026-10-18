<?php

use App\Http\Controllers\Finance\AccountController;
use App\Http\Controllers\Finance\ManualJournalController;
use App\Http\Controllers\Finance\PeriodController;
use App\Http\Controllers\Finance\ReportController;
use Illuminate\Support\Facades\Route;

// Read routes: Admin, Finance, Principal
Route::middleware(['auth', 'role:admin,finance,principal'])->group(function () {
    Route::get('finance/accounts', [AccountController::class, 'index'])->name('finance.accounts.index');

    Route::get('finance/journals', [ManualJournalController::class, 'index'])->name('finance.journals.index');
    Route::get('finance/journals/{journal}', [ManualJournalController::class, 'show'])
        ->whereNumber('journal')
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.show');
    Route::get('finance/journals/{journal}/attachment', [ManualJournalController::class, 'attachment'])
        ->missing(fn () => to_route('finance.journals.index'))
        ->name('finance.journals.attachment');

    // Ledger reports (Read-only for Admin, Finance, Principal)
    Route::get('finance/reports/general-journal', [ReportController::class, 'generalJournal'])
        ->name('finance.reports.general-journal');
    Route::get('finance/reports/general-ledger', [ReportController::class, 'generalLedger'])
        ->name('finance.reports.general-ledger');
    Route::get('finance/reports/trial-balance', [ReportController::class, 'trialBalance'])
        ->name('finance.reports.trial-balance');
    Route::get('finance/reports/cash-book', [ReportController::class, 'cashBook'])
        ->name('finance.reports.cash-book');
    Route::get('finance/reports/receivables-reconciliation', [ReportController::class, 'receivablesReconciliation'])
        ->name('finance.reports.receivables-reconciliation');
    Route::get('finance/reports/student-receivable-card', [ReportController::class, 'studentReceivableCard'])
        ->name('finance.reports.student-receivable-card');
    Route::get('finance/reports/receivables-aging', [ReportController::class, 'receivablesAging'])
        ->name('finance.reports.receivables-aging');
    Route::get('finance/reports/collections-by-class', [ReportController::class, 'collectionsByClass'])
        ->name('finance.reports.collections-by-class');

    Route::get('finance/periods', [PeriodController::class, 'index'])
        ->name('finance.periods.index');
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

    Route::get('finance/journals/create', [ManualJournalController::class, 'create'])->name('finance.journals.create');
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

    Route::post('finance/periods/{period}/close', [PeriodController::class, 'close'])
        ->name('finance.periods.close');
    Route::post('finance/periods/{period}/reopen', [PeriodController::class, 'reopen'])
        ->name('finance.periods.reopen');
    Route::post('finance/periods/year-end', [PeriodController::class, 'closeYear'])
        ->name('finance.periods.year-end');
});
