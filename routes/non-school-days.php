<?php

use App\Http\Controllers\Calendar\NonSchoolDayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('non-school-days', [NonSchoolDayController::class, 'index'])->name('non-school-days.index');
    Route::get('non-school-days/create', [NonSchoolDayController::class, 'create'])->name('non-school-days.create');
    Route::post('non-school-days', [NonSchoolDayController::class, 'store'])->name('non-school-days.store');
    Route::delete('non-school-days/{non_school_day}', [NonSchoolDayController::class, 'destroy'])
        ->missing(fn () => to_route('non-school-days.index'))
        ->name('non-school-days.destroy');
});
