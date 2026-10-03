<?php

use App\Http\Controllers\Teacher\CourseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher'])->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{class_subject}', [CourseController::class, 'show'])->name('courses.show');
});
