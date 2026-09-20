<?php

use App\Http\Controllers\Reports\ClassReportController;
use App\Http\Controllers\Reports\PresenceBoardController;
use App\Http\Controllers\Reports\StudentReportController;
use Illuminate\Support\Facades\Route;

// All four roles reach the student report — per-role student scoping
// happens in the controller (spec 06 / spec 01 §Data Scoping).
Route::middleware(['auth'])->group(function () {
    Route::get('reports/student', [StudentReportController::class, 'index'])->name('reports.student');
    Route::get('reports/student/export', [StudentReportController::class, 'export'])->name('reports.student.export');
});

Route::middleware(['auth', 'role:teacher,admin'])->group(function () {
    Route::get('reports/class', [ClassReportController::class, 'index'])->name('reports.class');
    Route::get('reports/class/export', [ClassReportController::class, 'export'])->name('reports.class.export');
    Route::get('presence-board', [PresenceBoardController::class, 'index'])->name('presence-board.index');
});
