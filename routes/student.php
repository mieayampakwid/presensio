<?php

use App\Http\Controllers\Student\GradeController;
use App\Http\Controllers\Student\ReportCardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('my-grades', [GradeController::class, 'index'])->name('my-grades.index');
    Route::get('my-report-cards', [ReportCardController::class, 'index'])->name('my-report-cards.index');
    Route::get('my-report-cards/{semester}/pdf', [ReportCardController::class, 'pdf'])->name('my-report-cards.pdf');
});
