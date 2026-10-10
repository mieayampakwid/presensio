<?php

use App\Http\Controllers\Subjects\SubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::post('subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::put('subjects/{subject}', [SubjectController::class, 'update'])
        ->missing(fn () => to_route('subjects.index'))
        ->name('subjects.update');
    Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])
        ->missing(fn () => to_route('subjects.index'))
        ->name('subjects.destroy');
});
