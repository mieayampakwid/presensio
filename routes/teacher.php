<?php

use App\Http\Controllers\Teacher\AssessmentController;
use App\Http\Controllers\Teacher\CourseController;
use App\Http\Controllers\Teacher\GradebookController;
use App\Http\Controllers\Teacher\LearningObjectiveController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher'])->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{class_subject}', [CourseController::class, 'show'])->name('courses.show');
});

Route::middleware(['auth', 'role:teacher,admin'])->group(function () {
    Route::get('objectives', [LearningObjectiveController::class, 'index'])->name('objectives.index');
    Route::post('objectives', [LearningObjectiveController::class, 'store'])->name('objectives.store');
    Route::post('objectives/copy', [LearningObjectiveController::class, 'copyPreviousYear'])->name('objectives.copy');
    Route::put('objectives/{learning_objective}', [LearningObjectiveController::class, 'update'])->name('objectives.update');
    Route::delete('objectives/{learning_objective}', [LearningObjectiveController::class, 'destroy'])->name('objectives.destroy');

    Route::get('courses/{class_subject}/assessments', [AssessmentController::class, 'index'])->name('courses.assessments.index');
    Route::post('courses/{class_subject}/assessments', [AssessmentController::class, 'store'])->name('courses.assessments.store');
    Route::put('courses/{class_subject}/assessments/{assessment}', [AssessmentController::class, 'update'])->name('courses.assessments.update');
    Route::delete('courses/{class_subject}/assessments/{assessment}', [AssessmentController::class, 'destroy'])->name('courses.assessments.destroy');
    Route::get('courses/{class_subject}/gradebook', [GradebookController::class, 'show'])->name('courses.gradebook');
});
