<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\GradebookBuilder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradebookController extends Controller
{
    public function __construct(
        private readonly GradebookBuilder $gradebookBuilder,
    ) {}

    /**
     * Display the gradebook grid for the course in the given semester.
     */
    public function show(Request $request, ClassSubject $classSubject): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && (
                ClassAccess::canWriteCourse($user, $classSubject) ||
                ClassAccess::academicClassIds($user)->contains($classSubject->class_id)
            ),
            403
        );

        $classSubject->loadMissing('schoolClass.academicYear.semesters');
        $semesters = $classSubject->schoolClass->academicYear->semesters;

        $semesterId = $request->filled('semester')
            ? (int) $request->input('semester')
            : (int) ($semesters->first()?->id ?? 0);

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $data = $this->gradebookBuilder->build($classSubject, $semester);
        $data['semesters'] = $semesters;
        $data['canWrite'] = ClassAccess::canWriteCourse($user, $classSubject);

        return Inertia::render('teacher/gradebook', $data);
    }
}
