<?php

namespace App\Services\Grading;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\LearningObjective;
use App\Models\Subject;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class LearningObjectiveViewService
{
    /**
     * Build the view data for the learning objectives index page.
     *
     * @return array{
     *     academicYears: EloquentCollection<int, AcademicYear>,
     *     subjects: Collection<int, mixed>|EloquentCollection<int, Subject>,
     *     gradeLevels: list<int>,
     *     selectedYearId: int,
     *     selectedSubjectId: int|null,
     *     selectedGradeLevel: int|null,
     *     objectives: EloquentCollection<int, LearningObjective>,
     *     canManage: bool,
     *     hasPreviousYear: bool
     * }
     */
    public function getIndexData(User $user, ?int $requestedYearId, ?int $requestedSubjectId, ?int $requestedGradeLevel): array
    {
        $activeYear = AcademicYear::active() ?? AcademicYear::latest('starts_at')->first();

        $yearId = $requestedYearId ?: (int) $activeYear?->id;
        $subjectId = $requestedSubjectId;
        $gradeLevel = $requestedGradeLevel;

        $academicYears = AcademicYear::query()
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'is_active']);

        if ($user->hasRole(UserRole::Admin)) {
            $subjects = Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);
            $gradeLevels = range(1, 12);
        } else {
            $teacher = $user->teacher;
            $classSubjects = $teacher !== null
                ? ClassSubject::query()
                    ->where('teacher_id', $teacher->id)
                    ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $yearId))
                    ->with(['subject:id,code,name', 'schoolClass:id,grade_level'])
                    ->get()
                : collect();

            $subjects = $classSubjects->pluck('subject')->filter()->unique('id')->values();
            $gradeLevels = array_values(array_map('intval', $classSubjects->pluck('schoolClass.grade_level')->filter()->unique()->sort()->values()->all()));

            if ($subjectId === null && $subjects->isNotEmpty()) {
                /** @var Subject $firstSubject */
                $firstSubject = $subjects->first();
                $subjectId = $firstSubject->id;
            }
            if ($gradeLevel === null && ! empty($gradeLevels)) {
                $gradeLevel = $gradeLevels[0];
            }
        }

        $canManage = false;
        /** @var EloquentCollection<int, LearningObjective> $objectives */
        $objectives = new EloquentCollection;
        $hasPreviousYear = false;

        if ($yearId && $subjectId && $gradeLevel) {
            $canManage = ClassAccess::canManageObjectives($user, $yearId, $subjectId, $gradeLevel);

            $objectives = LearningObjective::query()
                ->where('academic_year_id', $yearId)
                ->where('subject_id', $subjectId)
                ->where('grade_level', $gradeLevel)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get();

            $targetYear = AcademicYear::find($yearId);
            if ($targetYear) {
                $prevYear = AcademicYear::query()
                    ->where('id', '!=', $targetYear->id)
                    ->where('starts_at', '<', $targetYear->starts_at->toDateString())
                    ->orderByDesc('starts_at')
                    ->first();

                $hasPreviousYear = $prevYear !== null && LearningObjective::query()
                    ->where('academic_year_id', $prevYear->id)
                    ->where('subject_id', $subjectId)
                    ->where('grade_level', $gradeLevel)
                    ->exists();
            }
        }

        return [
            'academicYears' => $academicYears,
            'subjects' => $subjects,
            'gradeLevels' => $gradeLevels,
            'selectedYearId' => $yearId,
            'selectedSubjectId' => $subjectId,
            'selectedGradeLevel' => $gradeLevel,
            'objectives' => $objectives,
            'canManage' => $canManage,
            'hasPreviousYear' => $hasPreviousYear,
        ];
    }
}
