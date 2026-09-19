<?php

use App\Http\Controllers\Excuses\ExcuseAttachmentController;
use App\Http\Controllers\Excuses\ExcuseReviewController;
use App\Http\Controllers\Excuses\GuardianExcuseController;
use Illuminate\Support\Facades\Route;

// Guardian submissions (spec 04 §Requirements 1-2).
Route::middleware(['auth', 'role:parent'])->group(function () {
    Route::get('my-excuses', [GuardianExcuseController::class, 'index'])
        ->name('excuses.my');

    Route::post('excuses', [GuardianExcuseController::class, 'store'])
        ->name('excuses.store');
});

// Review queue: admins decide, teachers watch their homerooms read-only.
Route::middleware(['auth', 'role:teacher,admin'])->group(function () {
    Route::get('excuses', [ExcuseReviewController::class, 'index'])
        ->name('excuses.index');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::put('excuses/{excuse}/approve', [ExcuseReviewController::class, 'approve'])
        ->missing(fn () => to_route('excuses.index'))
        ->name('excuses.approve');

    Route::put('excuses/{excuse}/reject', [ExcuseReviewController::class, 'reject'])
        ->missing(fn () => to_route('excuses.index'))
        ->name('excuses.reject');
});

// Proof download — access mirrors excuse visibility (admin / owning
// guardian / homeroom teacher), enforced in the controller.
Route::middleware(['auth'])->group(function () {
    Route::get('excuses/{excuse}/attachment', [ExcuseAttachmentController::class, 'show'])
        ->missing(fn () => to_route('dashboard'))
        ->name('excuses.attachment');
});
