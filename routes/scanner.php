<?php

use App\Http\Controllers\Attendance\ScannerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:operate-scanner'])->group(function () {
    Route::get('scanner', [ScannerController::class, 'index'])->name('scanner.index');
    Route::post('scanner/scan', [ScannerController::class, 'store'])->middleware('throttle:web-scanner')->name('scanner.scan');
});
