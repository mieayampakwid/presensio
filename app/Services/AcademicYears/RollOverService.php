<?php

namespace App\Services\AcademicYears;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
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
    /**
     * @param  array<int, array{mode: 'existing'|'new'|'none', target_class_id?: int|null, new_name?: string|null, new_teacher_id?: int|null}>  $mappings
     *                                                                                                                                                     Keyed by source class id.
     */
    public function rollOver(AcademicYear $target, array $mappings, string $effectiveOn): void
    {
        DB::transaction(function () use ($target, $mappings, $effectiveOn): void {
            $source = AcademicYear::active();

            if ($source === null || $source->id === $target->id) {
                throw new InvalidArgumentException('Roll-over needs a target year other than the active one.');
            }

            $alreadyPromoted = Enrollment::query()
                ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $target->id))
                ->exists();

            if ($alreadyPromoted) {
                return; // idempotent re-apply
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

            AcademicYear::query()->whereKeyNot($target->id)->update(['is_active' => false]);
            $target->forceFill(['is_active' => true])->save();
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
        $sourceClassIds = $source->classes()->pluck('id');
        $targets = [];
        $created = [];

        foreach ($mappings as $sourceClassId => $mapping) {
            if (! $sourceClassIds->contains((int) $sourceClassId)) {
                throw new InvalidArgumentException('A mapped source class does not belong to the active year.');
            }

            $targetClassId = match ($mapping['mode']) {
                'none' => null,
                'existing' => $mapping['target_class_id'] ?? null,
                'new' => $created[$sourceClassId] ??= SchoolClass::query()->create([
                    'academic_year_id' => $target->id,
                    'name' => $mapping['new_name'] ?? throw new InvalidArgumentException('A new target class needs a name.'),
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
}
