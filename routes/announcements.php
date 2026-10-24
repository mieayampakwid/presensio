<?php

use App\Http\Controllers\Announcements\AnnouncementController;
use App\Http\Controllers\Announcements\AnnouncementReaderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('announcements/manage', [AnnouncementController::class, 'manage'])->name('announcements.manage');
    Route::get('announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::get('classes/{school_class}/announcements/create', [AnnouncementController::class, 'create'])->name('classes.announcements.create');
    Route::get('classes/{school_class}/announcements', [AnnouncementController::class, 'classIndex'])->name('classes.announcements.index');
    Route::post('classes/{school_class}/announcements', [AnnouncementController::class, 'store'])->name('classes.announcements.store');
    Route::get('announcements', [AnnouncementReaderController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::get('announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::post('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::get('announcements/{announcement}/attachment', [AnnouncementReaderController::class, 'attachment'])->name('announcements.attachment');
    Route::get('announcements/{announcement}/recipients', [AnnouncementReaderController::class, 'recipients'])->name('announcements.recipients');
    Route::post('announcements/{announcement}/acknowledge', [AnnouncementReaderController::class, 'acknowledge'])->name('announcements.acknowledge');
    Route::get('announcements/{announcement}', [AnnouncementReaderController::class, 'show'])->name('announcements.show');
});
