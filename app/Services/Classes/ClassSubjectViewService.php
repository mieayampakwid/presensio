<?php

namespace App\Services\Classes;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class ClassSubjectViewService
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
    ) {}

    /**
     * Check if a non-admin user can view class subjects.
     */
    public function canViewClassSubjects(User $user, SchoolClass $schoolClass): bool
    {
        $today = $this->schoolSettings->todayDate();

        return ClassAccess::canViewClassSubjects($user, $schoolClass, $today);
    }

    /**
     * Build the read-only view payload for non-admin roles (Teacher, Parent, Student, Principal).
     *
     * @return array{
     *     schoolClass: array<string, mixed>,
     *     classSubjects: Collection<int, array{
     *         id: int,
     *         subject_name: string,
     *         subject_code: string,
     *         subject_group: 'elective'|'general'|'local_content',
     *         teacher_name: string
     *     }>
     * }
     */
    public function getReadOnlyViewData(SchoolClass $schoolClass): array
    {
        $schoolClass->load([
            'academicYear:id,name,is_active',
            'teacher.employee:id,name',
            'classSubjects' => fn ($query) => $query->with([
                'subject:id,code,name,group,sort_order',
                'teacher.employee:id,name',
            ])->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                ->orderBy('subjects.group')
                ->orderBy('subjects.sort_order')
                ->orderBy('subjects.name')
                ->select('class_subjects.*'),
        ]);

        $classSubjects = $schoolClass->classSubjects->map(fn (ClassSubject $item): array => [
            'id' => $item->id,
            'subject_name' => $item->subject->name,
            'subject_code' => $item->subject->code,
            'subject_group' => $item->subject->group->value,
            'teacher_name' => $item->teacher !== null ? $item->teacher->employee->name : '',
        ]);

        return [
            'schoolClass' => $this->formatSchoolClassSummary($schoolClass),
            'classSubjects' => $classSubjects,
        ];
    }

    /**
     * Build the editable view payload for administrators.
     *
     * @return array{
     *     schoolClass: array<string, mixed>,
     *     classSubjects: Collection<int, array{
     *         id: int,
     *         class_id: int,
     *         subject_id: int,
     *         teacher_id: int,
     *         passing_threshold: string,
     *         subject: array{
     *             id: int,
     *             name: string,
     *             code: string,
     *             group: 'elective'|'general'|'local_content'
     *         },
     *         teacher: array{
     *             id: int,
     *             name: string
     *         }|null
     *     }>,
     *     subjects: EloquentCollection<int, Subject>,
     *     teachers: Collection<int, array{id: int, name: string}>,
     *     defaultPassingThreshold: string
     * }
     */
    public function getAdminViewData(SchoolClass $schoolClass): array
    {
        $schoolClass->load([
            'academicYear:id,name,is_active',
            'teacher.employee:id,name',
            'classSubjects' => fn ($query) => $query->with([
                'subject:id,code,name,group,sort_order,is_active',
                'teacher.employee:id,name',
            ])->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
                ->orderBy('subjects.group')
                ->orderBy('subjects.sort_order')
                ->orderBy('subjects.name')
                ->select('class_subjects.*'),
        ]);

        $availableSubjects = Subject::query()
            ->where('is_active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'group']);

        $availableTeachers = Teacher::query()
            ->active()
            ->with('employee')
            ->get()
            ->sortBy(fn (Teacher $t) => $t->employee->name)
            ->map(fn (Teacher $t): array => [
                'id' => $t->id,
                'name' => $t->employee->name,
            ])
            ->values();

        $classSubjectsPayload = $schoolClass->classSubjects->map(fn (ClassSubject $cs): array => [
            'id' => $cs->id,
            'class_id' => $cs->class_id,
            'subject_id' => $cs->subject_id,
            'teacher_id' => $cs->teacher_id,
            'passing_threshold' => (string) $cs->passing_threshold,
            'subject' => [
                'id' => $cs->subject->id,
                'name' => $cs->subject->name,
                'code' => $cs->subject->code,
                'group' => $cs->subject->group->value,
            ],
            'teacher' => $cs->teacher ? [
                'id' => $cs->teacher->id,
                'name' => $cs->teacher->employee->name,
            ] : null,
        ]);

        return [
            'schoolClass' => $this->formatSchoolClassSummary($schoolClass),
            'classSubjects' => $classSubjectsPayload,
            'subjects' => $availableSubjects,
            'teachers' => $availableTeachers,
            'defaultPassingThreshold' => $this->schoolSettings->defaultPassingThreshold(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSchoolClassSummary(SchoolClass $schoolClass): array
    {
        return [
            'id' => $schoolClass->id,
            'name' => $schoolClass->name,
            'grade_level' => $schoolClass->grade_level,
            'curriculum' => is_string($schoolClass->curriculum) ? $schoolClass->curriculum : $schoolClass->curriculum->value,
            'academic_year' => $schoolClass->academicYear ? [
                'id' => $schoolClass->academicYear->id,
                'name' => $schoolClass->academicYear->name,
                'is_active' => $schoolClass->academicYear->is_active,
            ] : null,
            'teacher' => $schoolClass->teacher ? [
                'id' => $schoolClass->teacher->id,
                'name' => $schoolClass->teacher->employee->name,
            ] : null,
        ];
    }
}
