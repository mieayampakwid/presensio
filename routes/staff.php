<?php

use App\Http\Controllers\Staff\StaffAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,principal'])->group(function () {
    Route::get('staff-attendance', [StaffAttendanceController::class, 'index'])->name('staff-attendance.index');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::put('staff-attendance', [StaffAttendanceController::class, 'upsert'])->name('staff-attendance.upsert');
});
