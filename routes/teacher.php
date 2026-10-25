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
use App\Http\Controllers\Teacher\ReportCardBundleController;
use App\Http\Controllers\Teacher\ReportCardPublicationController;
use App\Http\Controllers\Teacher\ReportCardWorkspaceController;
use App\Http\Controllers\Teacher\ScheduleController;
use App\Http\Controllers\Teacher\ScoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:teacher,admin,principal'])->group(function () {
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule');
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

    Route::get('classes/report-cards', [ReportCardWorkspaceController::class, 'index'])->name('classes.report-cards.index');
    Route::get('classes/{school_class}/report-cards', [ReportCardWorkspaceController::class, 'show'])->name('classes.report-cards.show');
    Route::put('classes/{school_class}/report-cards/entries/{student}', [ReportCardWorkspaceController::class, 'updateEntry'])->name('classes.report-cards.entries.update');
    Route::get('classes/{school_class}/report-cards/preview/{student}', [ReportCardWorkspaceController::class, 'preview'])->name('classes.report-cards.preview');
    Route::get('classes/{school_class}/report-cards/pdf/{student}', [ReportCardWorkspaceController::class, 'pdf'])->name('classes.report-cards.pdf');
    Route::get('classes/{school_class}/report-cards/preview-pdf/{student}', [ReportCardWorkspaceController::class, 'previewPdf'])->name('classes.report-cards.preview-pdf');
    Route::post('classes/{school_class}/report-cards/publish', [ReportCardPublicationController::class, 'publish'])->name('classes.report-cards.publish');
    Route::post('classes/{school_class}/report-cards/retract', [ReportCardPublicationController::class, 'retract'])->name('classes.report-cards.retract');
    Route::post('classes/{school_class}/report-cards/bundle', [ReportCardBundleController::class, 'store'])->name('classes.report-cards.bundle.store');
    Route::get('classes/{school_class}/report-cards/bundle/download', [ReportCardBundleController::class, 'download'])->name('classes.report-cards.bundle.download');
});
