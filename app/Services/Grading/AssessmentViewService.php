<?php

namespace App\Services\Grading;

use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\LearningObjective;
use App\Models\Semester;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class AssessmentViewService
{
    /**
     * Build the view data for the course assessments index page.
     *
     * @return array{
     *     course: array{
     *         id: int,
     *         class_name: string,
     *         grade_level: int,
     *         subject_name: string,
     *         subject_code: string,
     *         teacher_name: string|null,
     *         passing_threshold: string
     *     },
     *     currentSemester: Semester|null,
     *     semesters: EloquentCollection<int, Semester>,
     *     assessments: EloquentCollection<int, Assessment>,
     *     objectives: EloquentCollection<int, LearningObjective>,
     *     canWrite: bool
     * }
     */
    public function getIndexData(ClassSubject $classSubject, User $user, ?int $requestedSemesterId): array
    {
        $classSubject->load([
            'schoolClass.academicYear.semesters',
            'subject:id,code,name',
            'teacher.employee:id,name',
        ]);

        /** @var EloquentCollection<int, Semester> $semesters */
        $semesters = $classSubject->schoolClass->academicYear->semesters;
        $semesterId = $requestedSemesterId !== null
            ? $requestedSemesterId
            : (int) ($semesters->first()->id ?? 0);

        /** @var Semester|null $currentSemester */
        $currentSemester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();

        $assessments = Assessment::query()
            ->where('class_subject_id', $classSubject->id)
            ->where('semester_id', $semesterId)
            ->with(['learningObjectives:id,code,description'])
            ->withCount('scores')
            ->orderBy('sort_order')
            ->orderBy('assessed_on')
            ->get();

        $objectives = LearningObjective::query()
            ->where('academic_year_id', $classSubject->schoolClass->academic_year_id)
            ->where('subject_id', $classSubject->subject_id)
            ->where('grade_level', $classSubject->schoolClass->grade_level)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'code', 'description']);

        $canWrite = ClassAccess::canWriteCourse($user, $classSubject);

        return [
            'course' => [
                'id' => $classSubject->id,
                'class_name' => $classSubject->schoolClass->name,
                'grade_level' => $classSubject->schoolClass->grade_level,
                'subject_name' => $classSubject->subject->name,
                'subject_code' => $classSubject->subject->code,
                'teacher_name' => $classSubject->teacher?->employee?->name,
                'passing_threshold' => (string) $classSubject->passing_threshold,
            ],
            'currentSemester' => $currentSemester,
            'semesters' => $semesters,
            'assessments' => $assessments,
            'objectives' => $objectives,
            'canWrite' => $canWrite,
        ];
    }
}
