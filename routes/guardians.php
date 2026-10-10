<?php

use App\Http\Controllers\Guardians\GuardianController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('guardians', [GuardianController::class, 'index'])->name('guardians.index');
    Route::post('guardians', [GuardianController::class, 'store'])->name('guardians.store');
    Route::put('guardians/{guardian}', [GuardianController::class, 'update'])
        ->missing(fn () => to_route('guardians.index'))
        ->name('guardians.update');
    Route::delete('guardians/{guardian}', [GuardianController::class, 'destroy'])
        ->missing(fn () => to_route('guardians.index'))
        ->name('guardians.destroy');
});
