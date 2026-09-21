<?php

use App\Http\Controllers\AcademicYears\AcademicYearController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
    Route::post('academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
    Route::put('academic-years/{academic_year}', [AcademicYearController::class, 'update'])->name('academic-years.update');
    Route::post('academic-years/{academic_year}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
    Route::delete('academic-years/{academic_year}', [AcademicYearController::class, 'destroy'])->name('academic-years.destroy');

    Route::get('academic-years/roll-over', [AcademicYearController::class, 'rollOver'])->name('academic-years.roll-over');
    Route::post('academic-years/roll-over', [AcademicYearController::class, 'applyRollOver'])->name('academic-years.roll-over.apply');
});
