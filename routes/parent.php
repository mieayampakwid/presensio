<?php

use App\Http\Controllers\Parent\ChildGradeController;
use App\Http\Controllers\Parent\ChildReportCardController;
use App\Http\Controllers\Parent\FeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:parent,admin'])->group(function () {
    Route::get('children/report-cards', [ChildReportCardController::class, 'landing'])->name('children.report-cards.landing');
    Route::get('children/{student}/grades', [ChildGradeController::class, 'index'])->name('children.grades.index');
    Route::get('children/{student}/report-cards', [ChildReportCardController::class, 'index'])->name('children.report-cards.index');
    Route::get('children/{student}/report-cards/{semester}/pdf', [ChildReportCardController::class, 'pdf'])->name('children.report-cards.pdf');
    Route::get('my-fees', [FeeController::class, 'index'])->name('parent.fees.index');
    Route::post('my-fees/transfers', [FeeController::class, 'store'])->name('parent.fees.transfers.store');
});
