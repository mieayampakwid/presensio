<?php

use App\Http\Controllers\Students\StudentController;
use App\Http\Controllers\Students\StudentImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('students/import', [StudentImportController::class, 'create'])->name('students.import.create');
    Route::post('students/import', [StudentImportController::class, 'store'])->name('students.import.store');
    Route::post('students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('students/import/run', [StudentImportController::class, 'run'])->name('students.import.run');

    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])
        ->missing(fn () => to_route('students.index'))
        ->name('students.edit');
    Route::put('students/{student}', [StudentController::class, 'update'])
        ->missing(fn () => to_route('students.index'))
        ->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])
        ->missing(fn () => to_route('students.index'))
        ->name('students.destroy');
});
