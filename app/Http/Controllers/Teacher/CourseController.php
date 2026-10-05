<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
    ) {}

    /**
     * Display a listing of courses taught by the authenticated teacher in the active year.
     */
    public function index(Request $request): Response
    {
        $teacher = $request->user()?->teacher;
        $activeYear = AcademicYear::active();

        if ($teacher === null || $activeYear === null) {
            return Inertia::render('teacher/courses/index', [
                'courses' => [],
                'activeYear' => $activeYear,
            ]);
        }

        $courses = ClassSubject::query()
            ->where('teacher_id', $teacher->id)
            ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->with([
                'schoolClass:id,name,academic_year_id,grade_level',
                'subject:id,code,name,group',
            ])
            ->get();

        $classIds = $courses->pluck('class_id')->unique()->all();
        $today = $this->schoolSettings->todayDate();

        /** @var array<int, int> $rosterCounts */
        $rosterCounts = Enrollment::query()
            ->whereIn('class_id', $classIds)
            ->activeOn($today)
            ->groupBy('class_id')
            ->selectRaw('class_id, count(*) as count')
            ->pluck('count', 'class_id')
            ->all();

        $coursesPayload = $courses->map(fn (ClassSubject $course) => [
            'id' => $course->id,
            'class_id' => $course->class_id,
            'subject_id' => $course->subject_id,
            'teacher_id' => $course->teacher_id,
            'passing_threshold' => (string) $course->passing_threshold,
            'class_name' => $course->schoolClass->name,
            'grade_level' => $course->schoolClass->grade_level,
            'subject_name' => $course->subject->name,
            'subject_code' => $course->subject->code,
            'subject_group' => $course->subject->group->value,
            'students_count' => (int) ($rosterCounts[$course->class_id] ?? 0),
        ]);

        return Inertia::render('teacher/courses/index', [
            'courses' => $coursesPayload,
            'activeYear' => $activeYear,
        ]);
    }

    /**
     * Display course workspace: course info + enrolled students.
     */
    public function show(Request $request, ClassSubject $classSubject): Response
    {
        abort_unless(
            ClassAccess::academicClassIds($request->user())->contains($classSubject->class_id),
            403
        );

        $classSubject->load([
            'schoolClass:id,name,academic_year_id,grade_level',
            'subject:id,code,name,group',
            'teacher.employee:id,name',
        ]);

        $today = $this->schoolSettings->todayDate();

        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('class_id', $classSubject->class_id)->activeOn($today))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_number', 'gender']);

        return Inertia::render('teacher/courses/show', [
            'course' => [
                'id' => $classSubject->id,
                'class_id' => $classSubject->class_id,
                'subject_id' => $classSubject->subject_id,
                'passing_threshold' => (string) $classSubject->passing_threshold,
                'class_name' => $classSubject->schoolClass->name,
                'subject_name' => $classSubject->subject->name,
                'subject_code' => $classSubject->subject->code,
                'teacher_name' => $classSubject->teacher->employee->name,
            ],
            'students' => $students,
            'canWrite' => ClassAccess::canWriteCourse($request->user(), $classSubject),
        ]);
    }
}
