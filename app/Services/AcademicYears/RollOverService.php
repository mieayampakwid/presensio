<?php

namespace App\Services\AcademicYears;

use App\Enums\Curriculum;
use App\Models\AcademicYear;
use App\Models\ClassSubject;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The annual roll-over as one transactional use case (spec 07): end the
 * source year's open enrollments, reopen mapped students in the target
 * year's classes, leave unmapped students as alumni, and flip the active
 * year — so historical reports stay attributed to last year's rows while
 * every today-surface wakes up in the new year.
 */
class RollOverService
{
    public function __construct(private readonly SchoolSettings $schoolSettings) {}

    /**
     * @param  array<int, array{mode: 'existing'|'new'|'none', target_class_id?: int|null, new_name?: string|null, new_teacher_id?: int|null, copy_subjects?: bool|null}>  $mappings
     *                                                                                                                                                                                Keyed by source class id.
     * @return array{copied: int, skipped: list<array{subject: string, reason: string}>}
     */
    public function rollOver(AcademicYear $target, array $mappings, string $effectiveOn): array
    {
        return DB::transaction(function () use ($target, $mappings, $effectiveOn): array {
            $source = AcademicYear::active();

            if ($source === null || $source->id === $target->id) {
                throw new InvalidArgumentException('Roll-over needs a target year other than the active one.');
            }

            $alreadyPromoted = Enrollment::query()
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $target->id))
                ->exists();

            if ($alreadyPromoted) {
                return ['copied' => 0, 'skipped' => []]; // idempotent re-apply
            }

            $targets = $this->resolveTargets($target, $source, $mappings);

            $sourceClassIds = $source->classes()->pluck('id');

            $open = Enrollment::query()
                ->whereNull('ended_on')
                ->whereIn('class_id', $sourceClassIds)
                ->lockForUpdate()
                ->get();

            $endedOn = Date::parse($effectiveOn)->subDay()->toDateString();

            foreach ($open as $enrollment) {
                $enrollment->forceFill(['ended_on' => $endedOn])->save();

                $targetClassId = $targets[$enrollment->class_id] ?? null;

                if ($targetClassId !== null) {
                    Enrollment::query()->create([
                        'student_id' => $enrollment->student_id,
                        'class_id' => $targetClassId,
                        'started_on' => $effectiveOn,
                        'ended_on' => null,
                    ]);
                }
            }
            $subjectSummary = $this->copySubjectAssignments($targets, $mappings);

            AcademicYear::query()->whereKeyNot($target->id)->update(['is_active' => false]);
            $target->forceFill(['is_active' => true])->save();

            return $subjectSummary;
        });
    }

    /**
     * Validate every mapping and resolve the target class id per source
     * class (creating the "new" ones in the target year).
     *
     * @param  array<int, array{mode: 'existing'|'new'|'none', target_class_id?: int|null, new_name?: string|null, new_teacher_id?: int|null}>  $mappings
     * @return array<int, int> source class id => target class id
     */
    private function resolveTargets(AcademicYear $target, AcademicYear $source, array $mappings): array
    {
        $sourceClasses = $source->classes()->get()->keyBy('id');
        $targets = [];
        $created = [];

        $defaultCurriculum = $this->schoolSettings->row()->default_curriculum ?? Curriculum::Merdeka->value;

        foreach ($mappings as $sourceClassId => $mapping) {
            $sourceClass = $sourceClasses->get((int) $sourceClassId);
            if ($sourceClass === null) {
                throw new InvalidArgumentException('A mapped source class does not belong to the active year.');
            }

            $suggestedGradeLevel = min(12, max(1, $sourceClass->grade_level > 0 ? $sourceClass->grade_level + 1 : 1));

            $targetClassId = match ($mapping['mode']) {
                'none' => null,
                'existing' => $mapping['target_class_id'] ?? null,
                'new' => $created[$sourceClassId] ??= SchoolClass::query()->create([
                    'academic_year_id' => $target->id,
                    'name' => $mapping['new_name'] ?? throw new InvalidArgumentException('A new target class needs a name.'),
                    'grade_level' => $suggestedGradeLevel,
                    'curriculum' => $defaultCurriculum,
                    'teacher_id' => $mapping['new_teacher_id'] ?? null,
                ])->id,
            };

            if ($targetClassId !== null) {
                $targetClass = SchoolClass::query()->find($targetClassId);

                if ($targetClass === null || $targetClass->academic_year_id !== $target->id) {
                    throw new InvalidArgumentException('A mapped target class does not belong to the target year.');
                }
            }

            $targets[(int) $sourceClassId] = $targetClassId;
        }

        return $targets;
    }

    /**
     * Copy subject assignments from source classes to target classes.
     *
     * @param  array<int, int|null>  $targets  source class id => target class id
     * @param  array<int, array<string, mixed>>  $mappings
     * @return array{copied: int, skipped: list<array{subject: string, reason: string}>}
     */
    private function copySubjectAssignments(array $targets, array $mappings): array
    {
        $copiedCount = 0;
        $skipped = [];

        foreach ($targets as $sourceClassId => $targetClassId) {
            if ($targetClassId === null) {
                continue;
            }

            $mapping = $mappings[$sourceClassId] ?? [];
            if (empty($mapping['copy_subjects'])) {
                continue;
            }

            $sourceAssignments = ClassSubject::query()
                ->where('class_id', $sourceClassId)
                ->with(['subject', 'teacher.employee.user'])
                ->get();

            foreach ($sourceAssignments as $assignment) {
                if (! $assignment->subject->is_active) {
                    $skipped[] = [
                        'subject' => $assignment->subject->name,
                        'reason' => 'Subject is inactive',
                    ];

                    continue;
                }

                if ($assignment->teacher === null || ! $assignment->teacher->isActive()) {
                    $skipped[] = [
                        'subject' => $assignment->subject->name,
                        'reason' => 'Teacher is inactive',
                    ];

                    continue;
                }

                $alreadyAssigned = ClassSubject::query()
                    ->where('class_id', $targetClassId)
                    ->where('subject_id', $assignment->subject_id)
                    ->exists();

                if (! $alreadyAssigned) {
                    ClassSubject::create([
                        'class_id' => $targetClassId,
                        'subject_id' => $assignment->subject_id,
                        'teacher_id' => $assignment->teacher_id,
                        'passing_threshold' => $assignment->passing_threshold,
                    ]);
                    $copiedCount++;
                }
            }
        }

        return [
            'copied' => $copiedCount,
            'skipped' => $skipped,
        ];
    }
}
