<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateDescriptionRequest;
use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubjectResult;
use App\Services\Attendance\ClassAccess;
use App\Services\Grading\SubjectResultBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseResultController extends Controller
{
    public function __construct(
        private readonly SubjectResultBuilder $subjectResultBuilder,
    ) {}

    /**
     * Display the results and descriptions page for the course and semester.
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
            : (int) ($semesters->first()->id ?? 0);

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        $data = $this->subjectResultBuilder->build($classSubject, $semester);
        $data['semesters'] = $semesters;
        $data['canWrite'] = ClassAccess::canWriteCourse($user, $classSubject);

        return Inertia::render('teacher/results', $data);
    }

    /**
     * Update the achievement description for a student in this course semester.
     */
    public function updateDescription(
        UpdateDescriptionRequest $request,
        ClassSubject $classSubject,
        ?Student $student = null
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::canWriteCourse($user, $classSubject),
            403
        );

        if ($student === null) {
            $studentId = (int) $request->input('student_id');
            $student = Student::findOrFail($studentId);
        }

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

        $courseSemester = CourseSemester::forCourse($classSubject, $semester);
        $courseSemester->assertOpen();

        SubjectResult::updateOrCreate(
            [
                'course_semester_id' => $courseSemester->id,
                'student_id' => $student->id,
            ],
            [
                'description' => $request->validated('description'),
                'description_edited' => true,
            ]
        );

        return back()->with('success', 'Deskripsi capaian berhasil diperbarui.');
    }
}
