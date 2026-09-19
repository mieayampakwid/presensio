<?php

use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Attendance\StudentAttendanceController;
use App\Http\Controllers\Attendance\StudentQrController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('my-qr', [StudentQrController::class, 'show'])->name('attendance.my-qr');
    Route::get('my-attendance', [StudentAttendanceController::class, 'index'])->name('attendance.my-attendance');
});

Route::middleware(['auth', 'role:teacher,admin'])->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::put('attendance/record', [AttendanceController::class, 'updateRecord'])->name('attendance.record.update');
    Route::post('attendance/bulk-present', [AttendanceController::class, 'bulkPresent'])->name('attendance.bulk-present');
});
