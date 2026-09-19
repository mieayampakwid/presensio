<?php

use App\Http\Controllers\Attendance\StudentQrController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('my-qr', [StudentQrController::class, 'show'])->name('attendance.my-qr');
});
