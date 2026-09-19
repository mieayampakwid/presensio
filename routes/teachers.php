<?php

use App\Http\Controllers\Teachers\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::get('teachers/create', [TeacherController::class, 'create'])->name('teachers.create');
    Route::post('teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::get('teachers/{teacher}/edit', [TeacherController::class, 'edit'])
        ->missing(fn () => to_route('teachers.index'))
        ->name('teachers.edit');
    Route::put('teachers/{teacher}', [TeacherController::class, 'update'])
        ->missing(fn () => to_route('teachers.index'))
        ->name('teachers.update');
    Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])
        ->missing(fn () => to_route('teachers.index'))
        ->name('teachers.destroy');
});
