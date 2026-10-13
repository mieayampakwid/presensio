<?php

use App\Http\Controllers\Parent\ChildGradeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:parent,admin'])->group(function () {
    Route::get('children/{student}/grades', [ChildGradeController::class, 'index'])->name('children.grades.index');
});
