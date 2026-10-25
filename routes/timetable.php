<?php

use App\Http\Controllers\Timetable\BellScheduleController;
use App\Http\Controllers\Timetable\ClassTimetableController;
use App\Http\Controllers\Timetable\ClassTimetableViewController;
use Illuminate\Support\Facades\Route;

// Class weekly view: every role, authorization in the controller
// (spec 13 Requirement 3)
Route::middleware(['auth'])->group(function () {
    Route::get('classes/{school_class}/timetable', [ClassTimetableViewController::class, 'show'])
        ->name('classes.timetable.view');
});

// Bell schedule master: admin only (spec 13 Requirement 1)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('bell-schedules', [BellScheduleController::class, 'index'])->name('bell-schedules.index');
    Route::post('bell-schedules', [BellScheduleController::class, 'store'])->name('bell-schedules.store');
    Route::put('bell-schedules/{bell_schedule}', [BellScheduleController::class, 'update'])
        ->missing(fn () => to_route('bell-schedules.index'))
        ->name('bell-schedules.update');
    Route::delete('bell-schedules/{bell_schedule}', [BellScheduleController::class, 'destroy'])
        ->missing(fn () => to_route('bell-schedules.index'))
        ->name('bell-schedules.destroy');
});

// Class timetable editor: admin only (spec 13 Requirement 2)
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('classes/{school_class}/timetable/edit', [ClassTimetableController::class, 'edit'])
        ->name('classes.timetable.edit');
    Route::post('classes/{school_class}/timetable/copy', [ClassTimetableController::class, 'copy'])
        ->name('classes.timetable.copy');
    Route::post('classes/{school_class}/timetable/slots', [ClassTimetableController::class, 'store'])
        ->name('classes.timetable.slots.store');
    Route::put('classes/{school_class}/timetable/slots/{slot}', [ClassTimetableController::class, 'update'])
        ->name('classes.timetable.slots.update');
    Route::delete('classes/{school_class}/timetable/slots/{slot}', [ClassTimetableController::class, 'destroy'])
        ->name('classes.timetable.slots.destroy');
});
