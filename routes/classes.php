<?php

use App\Http\Controllers\Classes\SchoolClassController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('classes', [SchoolClassController::class, 'index'])->name('classes.index');
    Route::get('classes/create', [SchoolClassController::class, 'create'])->name('classes.create');
    Route::post('classes', [SchoolClassController::class, 'store'])->name('classes.store');
    Route::get('classes/{school_class}/edit', [SchoolClassController::class, 'edit'])
        ->missing(fn () => to_route('classes.index'))
        ->name('classes.edit');
    Route::put('classes/{school_class}', [SchoolClassController::class, 'update'])
        ->missing(fn () => to_route('classes.index'))
        ->name('classes.update');
    Route::delete('classes/{school_class}', [SchoolClassController::class, 'destroy'])
        ->missing(fn () => to_route('classes.index'))
        ->name('classes.destroy');
});
