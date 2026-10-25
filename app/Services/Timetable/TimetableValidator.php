<?php

namespace App\Services\Timetable;

use App\Enums\PeriodKind;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Services\SchoolSettings;

/**
 * Pure-ish slot placement checks for the timetable editor (spec 13).
 * Returns localized error/warning data; the caller maps them to HTTP.
 */
class TimetableValidator
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * Hard errors (AC-13-02/03): the day must be operational, the starting
     * period a lesson of the bell schedule covering that day, the span free
     * of breaks, and free of overlap with the class's other slots in the
     * same semester and day.
     *
     * @param  ?int  $ignoreSlotId  the slot being updated (excluded from the overlap scan)
     * @return list<string> localized error messages, empty when the placement is valid
     */
    public function errors(
        SchoolClass $class,
        Semester $semester,
        int $dayOfWeek,
        LessonPeriod $startPeriod,
        int $periodCount,
        ?int $ignoreSlotId = null,
    ): array {
        $errors = [];

        if (! in_array($dayOfWeek, $this->settings->operationalWeekdays(), true)) {
            $errors[] = __('timetable.errors.slot_day_not_operational', [
                'day' => __('timetable.weekdays.'.$dayOfWeek),
            ]);

            return $errors;
        }

        $schedule = $this->scheduleForDay($dayOfWeek);
        if ($schedule === null || $schedule->id !== $startPeriod->bell_schedule_id) {
            $errors[] = __('timetable.errors.slot_schedule_mismatch');

            return $errors;
        }

        if ($startPeriod->kind !== PeriodKind::Lesson) {
            $errors[] = __('timetable.errors.slot_start_not_lesson');

            return $errors;
        }

        $periods = $schedule->periods()->get();
        $index = $periods->search(fn (LessonPeriod $period): bool => $period->is($startPeriod));
        $span = $periods->slice((int) $index, $periodCount);
        if ($span->count() < $periodCount || $span->contains(
            fn (LessonPeriod $period): bool => $period->kind !== PeriodKind::Lesson
        )) {
            $errors[] = __('timetable.errors.slot_span_invalid');

            return $errors;
        }

        $from = (int) $startPeriod->number;
        $to = $from + $periodCount;
        $overlaps = TimetableSlot::query()
            ->where('semester_id', $semester->id)
            ->where('class_id', $class->id)
            ->where('day_of_week', $dayOfWeek)
            ->when($ignoreSlotId !== null, fn ($query) => $query->whereKeyNot($ignoreSlotId))
            ->with('lessonPeriod')
            ->get()
            ->contains(fn (TimetableSlot $slot): bool => $this->overlaps($slot, $from, $to));

        if ($overlaps) {
            $errors[] = __('timetable.errors.slot_overlap');
        }

        return $errors;
    }

    /**
     * Teacher/room double-booking warnings (AC-13-04) — not blocking: the
     * same teacher (via class_subjects.teacher_id) or the same non-empty
     * room in ANOTHER class, same semester and day, at an overlapping range.
     * The class being edited is excluded, so an updated slot never matches
     * against itself.
     *
     * @return list<array{type: string, message: string}>
     */
    public function conflicts(
        SchoolClass $class,
        Semester $semester,
        int $dayOfWeek,
        LessonPeriod $startPeriod,
        int $periodCount,
        ?ClassSubject $classSubject,
        ?string $room,
    ): array {
        $from = (int) $startPeriod->number;
        $to = $from + $periodCount;
        $conflicts = [];

        if ($classSubject !== null) {
            TimetableSlot::query()
                ->where('semester_id', $semester->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('class_id', '!=', $class->id)
                ->whereHas('classSubject', fn ($query) => $query->where('teacher_id', $classSubject->teacher_id))
                ->with('lessonPeriod', 'schoolClass:id,name')
                ->get()
                ->each(function (TimetableSlot $slot) use (&$conflicts, $from, $to): void {
                    if ($this->overlaps($slot, $from, $to)) {
                        $conflicts[] = [
                            'type' => 'teacher',
                            'message' => __('timetable.warnings.teacher', [
                                'class' => $slot->schoolClass->name,
                            ]),
                        ];
                    }
                });
        }

        if ($room !== null && trim($room) !== '') {
            TimetableSlot::query()
                ->where('semester_id', $semester->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('class_id', '!=', $class->id)
                ->where('room', $room)
                ->with('lessonPeriod', 'schoolClass:id,name')
                ->get()
                ->each(function (TimetableSlot $slot) use (&$conflicts, $from, $to, $room): void {
                    if ($this->overlaps($slot, $from, $to)) {
                        $conflicts[] = [
                            'type' => 'room',
                            'message' => __('timetable.warnings.room', [
                                'class' => $slot->schoolClass->name,
                                'room' => $room,
                            ]),
                        ];
                    }
                });
        }

        return $conflicts;
    }

    /**
     * The bell schedule covering the ISO weekday — at most one per day
     * (AC-13-01 coverage invariant).
     */
    private function scheduleForDay(int $dayOfWeek): ?BellSchedule
    {
        return BellSchedule::query()
            ->get()
            ->first(fn (BellSchedule $schedule): bool => in_array(
                $dayOfWeek,
                array_map(intval(...), $schedule->weekdays),
                true,
            ));
    }

    /**
     * Whether the slot's lesson-number range [start, start + period_count)
     * intersects [from, to).
     */
    private function overlaps(TimetableSlot $slot, int $from, int $to): bool
    {
        $start = (int) $slot->lessonPeriod->number;
        $end = $start + $slot->period_count;

        return $from < $end && $start < $to;
    }
}
