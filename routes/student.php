<?php

use App\Http\Controllers\Student\GradeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('my-grades', [GradeController::class, 'index'])->name('my-grades.index');
});
