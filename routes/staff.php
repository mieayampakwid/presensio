<?php

use App\Http\Controllers\Staff\MyLeaveController;
use App\Http\Controllers\Staff\StaffAttendanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,principal'])->group(function () {
    Route::get('staff-attendance', [StaffAttendanceController::class, 'index'])->name('staff-attendance.index');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::put('staff-attendance', [StaffAttendanceController::class, 'upsert'])->name('staff-attendance.upsert');
});

// Employee self-service leave requests (spec 16 §Req 4): any
// authenticated user with an employee profile; the controller enforces
// the profile check (403 otherwise), so no role middleware here.
Route::middleware(['auth'])->group(function () {
    Route::get('my-leave', [MyLeaveController::class, 'index'])->name('my-leave.index');
    Route::post('my-leave', [MyLeaveController::class, 'store'])->name('my-leave.store');
    Route::delete('my-leave/{leave_request}', [MyLeaveController::class, 'destroy'])->name('my-leave.destroy');
    Route::get('my-leave/{leave_request}/attachment', [MyLeaveController::class, 'attachment'])->name('my-leave.attachment');
});
