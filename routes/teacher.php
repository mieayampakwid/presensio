<?php

use App\Http\Controllers\Teacher\AssessmentController;
use App\Http\Controllers\Teacher\AssessmentReleaseController;
use App\Http\Controllers\Teacher\ClassGradeLedgerController;
use App\Http\Controllers\Teacher\CourseController;
use App\Http\Controllers\Teacher\CourseFinalizationController;
use App\Http\Controllers\Teacher\CourseResultController;
use App\Http\Controllers\Teacher\CourseWeightController;
use App\Http\Controllers\Teacher\GradebookController;
use App\Http\Controllers\Teacher\LearningObjectiveController;
use App\Http\Controllers\Teacher\ScoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher,admin,principal'])->group(function () {
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{class_subject}', [CourseController::class, 'show'])->name('courses.show');
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
    Route::post('courses/{class_subject}/scores', [ScoreController::class, 'store'])->name('courses.scores.store');
    Route::put('courses/{class_subject}/weights', [CourseWeightController::class, 'update'])->name('courses.weights.update');
    Route::post('courses/{class_subject}/assessments/{assessment}/release', [AssessmentReleaseController::class, 'release'])->name('courses.assessments.release');
    Route::get('courses/{class_subject}/results', [CourseResultController::class, 'show'])->name('courses.results.show');
    Route::put('courses/{class_subject}/results/{student}', [CourseResultController::class, 'updateDescription'])->name('courses.results.description.update');
    Route::put('courses/{class_subject}/results', [CourseResultController::class, 'updateDescription'])->name('courses.results.update');
    Route::post('courses/{class_subject}/finalize', [CourseFinalizationController::class, 'finalize'])->name('courses.finalize');
    Route::delete('courses/{class_subject}/finalize', [CourseFinalizationController::class, 'undo'])->name('courses.unfinalize');
    Route::get('classes/{school_class}/grades', [ClassGradeLedgerController::class, 'index'])->name('classes.grades.index');
});
