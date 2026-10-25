<?php

namespace App\Services\Timetable;

use App\Models\BellSchedule;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\AcademicYears\SemesterService;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Read-only timetable view models shared by the editor and the weekly
 * views. Slot times are always derived from the bell schedule periods
 * (spec 13: times live only in the bell schedule).
 */
class TimetableView
{
    /**
     * The semester being viewed: the `semester` query param when it belongs
     * to the class's academic year, else the current semester, else the
     * year's first semester (past-year classes have no current semester).
     */
    public function resolveSemester(Request $request, SchoolClass $schoolClass): Semester
    {
        $semesters = $schoolClass->academicYear->semesters;

        $requestedId = (int) $request->query('semester');
        $currentId = app(SemesterService::class)->current()?->id;

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $requestedId > 0 ? $requestedId : null)
            ?? $semesters->firstWhere('id', $currentId)
            ?? $semesters->first();

        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        return $semester;
    }

    /**
     * The class's weekly grid: operational days × that day's bell schedule
     * periods with the semester's slots on each day.
     *
     * @return list<array{weekday: int, periods: list<array{id: int, kind: string, number: int|null, name: string|null, start_time: string, end_time: string}>, slots: list<array{id: int, day_of_week: int, lesson_period_id: int, period_count: int, class_subject_id: int|null, activity_name: string|null, room: string|null, title: string, teacher_name: string|null, start_time: string, end_time: string}>}>
     */
    public function days(SchoolClass $schoolClass, Semester $semester): array
    {
        $schedules = BellSchedule::query()->with('periods')->get();
        $slots = TimetableSlot::query()
            ->where('semester_id', $semester->id)
            ->where('class_id', $schoolClass->id)
            ->with([
                'lessonPeriod',
                'classSubject.subject:id,name',
                'classSubject.teacher.employee:id,name',
            ])
            ->get();

        return array_values(collect(app(SchoolSettings::class)->operationalWeekdays())
            ->map(fn (int $weekday): array => $this->dayPayload(
                $weekday,
                $this->scheduleForDay($schedules, $weekday),
                $slots,
            ))
            ->all());
    }

    /**
     * The teacher's personal weekly schedule for the semester (their
     * class_subjects slots), grouped by operational day; today's current/
     * next slot highlighted in the school timezone.
     *
     * @return list<array{weekday: int, slots: list<array{id: int, class_name: string, title: string, room: string|null, start_time: string, end_time: string, status: string|null}>}>
     */
    public function teacherSchedule(Teacher $teacher, Semester $semester): array
    {
        $schedules = BellSchedule::query()->with('periods')->get();
        $slots = TimetableSlot::query()
            ->where('semester_id', $semester->id)
            ->whereHas('classSubject', fn ($query) => $query->where('teacher_id', $teacher->id))
            ->with([
                'lessonPeriod',
                'classSubject.subject:id,name',
                'classSubject.schoolClass:id,name',
            ])
            ->get();

        $now = app(SchoolSettings::class)->now();
        $today = $now->dayOfWeekIso;
        $clock = $now->format('H:i');

        return array_values(collect(app(SchoolSettings::class)->operationalWeekdays())
            ->map(function (int $weekday) use ($schedules, $slots, $today, $clock): array {
                $periods = $this->scheduleForDay($schedules, $weekday)?->periods->values() ?? new Collection;

                $daySlots = $slots
                    ->where('day_of_week', $weekday)
                    ->sortBy(fn (TimetableSlot $slot): int => (int) $periods->search(
                        fn (LessonPeriod $period): bool => $period->is($slot->lessonPeriod),
                    ))
                    ->map(fn (TimetableSlot $slot): array => $this->assignmentPayload($slot, $periods))
                    ->values()
                    ->all();

                return [
                    'weekday' => $weekday,
                    'slots' => array_values($weekday === $today
                        ? $this->withNowStatus($daySlots, $clock)
                        : $daySlots),
                ];
            })
            ->all());
    }

    /**
     * The bell schedule covering an ISO weekday — at most one per day
     * (AC-13-01 coverage invariant).
     *
     * @param  Collection<int, BellSchedule>  $schedules
     */
    private function scheduleForDay(Collection $schedules, int $weekday): ?BellSchedule
    {
        return $schedules->first(
            fn (BellSchedule $schedule): bool => in_array(
                $weekday,
                array_map(intval(...), $schedule->weekdays),
                true,
            ),
        );
    }

    /**
     * Grid payload for one operational weekday: the day's bell schedule
     * periods and the semester's slots on that day.
     *
     * @param  Collection<int, TimetableSlot>  $slots
     * @return array{weekday: int, periods: list<array{id: int, kind: string, number: int|null, name: string|null, start_time: string, end_time: string}>, slots: list<array{id: int, day_of_week: int, lesson_period_id: int, period_count: int, class_subject_id: int|null, activity_name: string|null, room: string|null, title: string, teacher_name: string|null, start_time: string, end_time: string}>}
     */
    private function dayPayload(int $weekday, ?BellSchedule $schedule, Collection $slots): array
    {
        if ($schedule === null) {
            return [
                'weekday' => $weekday,
                'periods' => [],
                'slots' => [],
            ];
        }

        $periods = $schedule->periods->values();

        return [
            'weekday' => $weekday,
            'periods' => array_values($periods
                ->map(fn (LessonPeriod $period): array => [
                    'id' => $period->id,
                    'kind' => $period->kind->value,
                    'number' => $period->number,
                    'name' => $period->name,
                    'start_time' => substr((string) $period->start_time, 0, 5),
                    'end_time' => substr((string) $period->end_time, 0, 5),
                ])
                ->all()),
            'slots' => array_values($slots
                ->where('day_of_week', $weekday)
                ->map(fn (TimetableSlot $slot): array => $this->slotPayload($slot, $periods))
                ->all()),
        ];
    }

    /**
     * @param  Collection<int, LessonPeriod>  $periods  the day's ordered periods
     * @return array{id: int, day_of_week: int, lesson_period_id: int, period_count: int, class_subject_id: int|null, activity_name: string|null, room: string|null, title: string, teacher_name: string|null, start_time: string, end_time: string}
     */
    private function slotPayload(TimetableSlot $slot, Collection $periods): array
    {
        $subject = $slot->classSubject;
        $times = $this->derivedTimes($slot, $periods);

        return [
            'id' => $slot->id,
            'day_of_week' => $slot->day_of_week,
            'lesson_period_id' => $slot->lesson_period_id,
            'period_count' => $slot->period_count,
            'class_subject_id' => $slot->class_subject_id,
            'activity_name' => $slot->activity_name,
            'room' => $slot->room,
            'title' => $subject !== null ? $subject->subject->name : (string) $slot->activity_name,
            'teacher_name' => $subject?->teacher?->employee?->name,
            'start_time' => $times['start'],
            'end_time' => $times['end'],
        ];
    }

    /**
     * A teacher-schedule entry: the slot's class, subject, room and derived
     * times. `status` is null everywhere except today's current/next slot
     * (set by withNowStatus).
     *
     * @param  Collection<int, LessonPeriod>  $periods  the day's ordered periods
     * @return array{id: int, class_name: string, title: string, room: string|null, start_time: string, end_time: string, status: string|null}
     */
    private function assignmentPayload(TimetableSlot $slot, Collection $periods): array
    {
        $subject = $slot->classSubject;
        $times = $this->derivedTimes($slot, $periods);

        return [
            'id' => $slot->id,
            'class_name' => $subject?->schoolClass->name ?? '',
            'title' => $subject?->subject->name ?? (string) $slot->activity_name,
            'room' => $slot->room,
            'start_time' => $times['start'],
            'end_time' => $times['end'],
            'status' => null,
        ];
    }

    /**
     * The slot's start/end times derived from the day's periods: the start
     * period's start through the end of the `period_count`-th consecutive
     * lesson period.
     *
     * @param  Collection<int, LessonPeriod>  $periods  the day's ordered periods
     * @return array{start: string, end: string}
     */
    private function derivedTimes(TimetableSlot $slot, Collection $periods): array
    {
        $index = $periods->search(fn (LessonPeriod $period): bool => $period->is($slot->lessonPeriod));
        $last = $periods->slice((int) $index, $slot->period_count)->last();

        return [
            'start' => substr((string) $slot->lessonPeriod->start_time, 0, 5),
            'end' => $last !== null ? substr((string) $last->end_time, 0, 5) : substr((string) $slot->lessonPeriod->end_time, 0, 5),
        ];
    }

    /**
     * Mark the slot in progress ("current") and the next one ahead
     * ("next") from the school-timezone clock. H:i strings compare
     * lexicographically.
     *
     * @param  array<int, array{id: int, class_name: string, title: string, room: string|null, start_time: string, end_time: string, status: string|null}>  $slots
     * @return array<int, array{id: int, class_name: string, title: string, room: string|null, start_time: string, end_time: string, status: string|null}>
     */
    private function withNowStatus(array $slots, string $clock): array
    {
        $afterCurrent = 0;

        foreach ($slots as $index => $slot) {
            if ($clock >= $slot['start_time'] && $clock <= $slot['end_time']) {
                $slots[$index]['status'] = 'current';
                $afterCurrent = (int) $index + 1;

                break;
            }
        }

        $count = count($slots);
        for ($index = $afterCurrent; $index < $count; $index++) {
            if ($clock < $slots[$index]['start_time']) {
                $slots[$index]['status'] = 'next';

                break;
            }
        }

        return $slots;
    }
}
