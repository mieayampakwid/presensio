<?php

use App\Http\Controllers\Api\AttendanceScanController;
use App\Http\Middleware\EnsureValidScannerKey;
use Illuminate\Support\Facades\Route;

// Hardware scanner surface (spec 03). The framework `api` group is
// stateless and ships no default throttle — the named `scanner` limiter is
// load-bearing, do not remove it.
Route::middleware([EnsureValidScannerKey::class, 'throttle:scanner'])
    ->post('/attendance/scan', AttendanceScanController::class)
    ->name('attendance.scan');
