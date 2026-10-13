<?php

namespace App\Services\Courses;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class CourseViewService
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
    ) {}

    /**
     * Build the courses listing payload for the given user in the active academic year.
     *
     * @return array{
     *     courses: array<int, array<string, mixed>>,
     *     activeYear: AcademicYear|null,
     *     isAdmin: bool
     * }
     */
    public function listForUser(User $user): array
    {
        $isAdmin = $user->hasRole(UserRole::Admin) && $user->activeRole() === UserRole::Admin;
        $teacher = $user->teacher;
        $activeYear = AcademicYear::active();

        if ($activeYear === null || (! $isAdmin && $teacher === null)) {
            return [
                'courses' => [],
                'activeYear' => $activeYear,
                'isAdmin' => $isAdmin,
            ];
        }

        $query = ClassSubject::query()
            ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $activeYear->id))
            ->with([
                'schoolClass:id,name,academic_year_id,grade_level',
                'subject:id,code,name,group',
                'teacher.employee:id,name',
            ]);

        if (! $isAdmin) {
            $query->where('teacher_id', $teacher->id);
        }

        /** @var EloquentCollection<int, ClassSubject> $courses */
        $courses = $query->get()->sortBy([
            ['schoolClass.name', 'asc'],
            ['subject.name', 'asc'],
        ]);

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
            'teacher_name' => $course->teacher?->employee->name ?? 'Belum ditentukan',
            'students_count' => (int) ($rosterCounts[$course->class_id] ?? 0),
        ])->values()->all();

        return [
            'courses' => $coursesPayload,
            'activeYear' => $activeYear,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Build the course workspace payload.
     *
     * @return array{
     *     course: array<string, mixed>,
     *     students: Collection<int, Student>,
     *     canWrite: bool
     * }
     */
    public function buildWorkspace(ClassSubject $classSubject, User $user): array
    {
        $classSubject->loadMissing([
            'schoolClass:id,name,academic_year_id,grade_level',
            'subject:id,code,name,group',
            'teacher.employee:id,name',
        ]);

        $today = $this->schoolSettings->todayDate();

        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('class_id', $classSubject->class_id)->activeOn($today))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_number', 'gender']);

        return [
            'course' => [
                'id' => $classSubject->id,
                'class_id' => $classSubject->class_id,
                'subject_id' => $classSubject->subject_id,
                'passing_threshold' => (string) $classSubject->passing_threshold,
                'class_name' => $classSubject->schoolClass->name,
                'subject_name' => $classSubject->subject->name,
                'subject_code' => $classSubject->subject->code,
                'teacher_name' => $classSubject->teacher?->employee->name ?? 'Belum ditentukan',
            ],
            'students' => $students,
            'canWrite' => ClassAccess::canWriteCourse($user, $classSubject),
        ];
    }
}
