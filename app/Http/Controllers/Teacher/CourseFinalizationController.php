<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\CourseFinalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CourseFinalizationController extends Controller
{
    public function __construct(
        private readonly CourseFinalizer $courseFinalizer,
    ) {}

    /**
     * Finalize the course for the given semester.
     */
    public function finalize(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::canWriteCourse($user, $classSubject),
            403
        );

        $classSubject->loadMissing('schoolClass.academicYear.semesters');
        $semesters = $classSubject->schoolClass->academicYear->semesters;

        $semesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : ($request->filled('semester')
                ? (int) $request->input('semester')
                : (int) ($semesters->first()->id ?? 0));

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $this->courseFinalizer->finalize($classSubject, $semester, $user);

        return back()->with('success', 'Mata pelajaran berhasil difinalisasi.');
    }

    /**
     * Undo finalization for the course in the given semester.
     */
    public function undo(Request $request, ClassSubject $classSubject): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::canWriteCourse($user, $classSubject),
            403
        );

        $classSubject->loadMissing('schoolClass.academicYear.semesters');
        $semesters = $classSubject->schoolClass->academicYear->semesters;

        $semesterId = $request->filled('semester_id')
            ? (int) $request->input('semester_id')
            : ($request->filled('semester')
                ? (int) $request->input('semester')
                : (int) ($semesters->first()->id ?? 0));

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $this->courseFinalizer->undo($classSubject, $semester, $user);

        return back()->with('success', 'Finalisasi mata pelajaran berhasil dibatalkan.');
    }
}
