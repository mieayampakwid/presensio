<?php

namespace App\Services\Timetable;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies a class's slots from the previous semester into the target one
 * (spec 13, AC-13-07). Slots whose class_subjects row no longer exists in
 * the class, and slots whose target position is already occupied, are
 * skipped and reported so the UI can summarize them.
 */
class TimetableCopier
{
    public const REASON_SUBJECT_REMOVED = 'subject_removed';

    public const REASON_OCCUPIED = 'occupied';

    /**
     * @return array{copied: int, skipped: list<array{day_of_week: int, lesson_number: int, reason: string}>}
     */
    public function copy(SchoolClass $class, Semester $target): array
    {
        $source = $this->previousSemester($target);

        if ($source === null) {
            throw ValidationException::withMessages([
                'semester_id' => __('timetable.errors.copy_no_source'),
            ]);
        }

        return DB::transaction(function () use ($class, $source, $target): array {
            $sourceSlots = TimetableSlot::query()
                ->where('semester_id', $source->id)
                ->where('class_id', $class->id)
                ->with('lessonPeriod', 'classSubject')
                ->get()
                ->sortBy(fn (TimetableSlot $slot): array => [
                    $slot->day_of_week,
                    (int) $slot->lessonPeriod->number,
                ])
                ->values();

            $occupied = $this->occupiedRanges($class, $target);

            $copied = 0;
            $skipped = [];

            foreach ($sourceSlots as $slot) {
                $entry = [
                    'day_of_week' => $slot->day_of_week,
                    'lesson_number' => (int) $slot->lessonPeriod->number,
                ];

                if (! $this->subjectStillExists($slot, $class)) {
                    $skipped[] = $entry + ['reason' => self::REASON_SUBJECT_REMOVED];

                    continue;
                }

                $range = [
                    (int) $slot->lessonPeriod->number,
                    (int) $slot->lessonPeriod->number + $slot->period_count,
                ];

                if (collect($occupied[$slot->day_of_week] ?? [])
                    ->contains(fn (array $existing): bool => $this->overlaps($existing, $range))
                ) {
                    $skipped[] = $entry + ['reason' => self::REASON_OCCUPIED];

                    continue;
                }

                TimetableSlot::create([
                    'semester_id' => $target->id,
                    'class_id' => $class->id,
                    'day_of_week' => $slot->day_of_week,
                    'lesson_period_id' => $slot->lesson_period_id,
                    'period_count' => $slot->period_count,
                    'class_subject_id' => $slot->class_subject_id,
                    'activity_name' => $slot->activity_name,
                    'room' => $slot->room,
                ]);

                $occupied[$slot->day_of_week][] = $range;
                $copied++;
            }

            return ['copied' => $copied, 'skipped' => $skipped];
        });
    }

    /**
     * The semester to copy from, by ordering: semester 1 of a year pulls
     * from semester 2 of the previous academic year; semester 2 pulls from
     * semester 1 of the same year.
     */
    private function previousSemester(Semester $target): ?Semester
    {
        if ($target->number === 2) {
            return Semester::query()
                ->where('academic_year_id', $target->academic_year_id)
                ->where('number', 1)
                ->first();
        }

        // Bind a date string, not a Carbon datetime: starts_at is stored as
        // Y-m-d and a datetime binding makes the year match itself on
        // sqlite/pgsql string-vs-date comparisons.
        $previousYear = AcademicYear::query()
            ->where('starts_at', '<', $target->academicYear->starts_at->toDateString())
            ->orderByDesc('starts_at')
            ->first();

        return $previousYear?->semesters()->where('number', 2)->first();
    }

    /**
     * Lesson-number ranges of the class's existing slots in the target
     * semester, keyed by ISO weekday.
     *
     * @return array<int, list<array{int, int}>>
     */
    private function occupiedRanges(SchoolClass $class, Semester $target): array
    {
        return TimetableSlot::query()
            ->where('semester_id', $target->id)
            ->where('class_id', $class->id)
            ->with('lessonPeriod')
            ->get()
            ->mapToGroups(fn (TimetableSlot $slot): array => [
                $slot->day_of_week => [
                    (int) $slot->lessonPeriod->number,
                    (int) $slot->lessonPeriod->number + $slot->period_count,
                ],
            ])
            ->map(fn (Collection $ranges): array => array_values($ranges->all()))
            ->all();
    }

    /**
     * A subject slot can only be copied when its class_subjects row still
     * exists for this class; activity slots always qualify.
     */
    private function subjectStillExists(TimetableSlot $slot, SchoolClass $class): bool
    {
        if ($slot->class_subject_id === null) {
            return true;
        }

        return $slot->classSubject !== null
            && $slot->classSubject->class_id === $class->id;
    }

    /**
     * Whether two lesson-number ranges [start, start + period_count) intersect.
     *
     * @param  array{int, int}  $existing
     * @param  array{int, int}  $candidate
     */
    private function overlaps(array $existing, array $candidate): bool
    {
        return $candidate[0] < $existing[1] && $existing[0] < $candidate[1];
    }
}
