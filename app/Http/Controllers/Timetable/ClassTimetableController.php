<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\SaveSlotRequest;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Services\AcademicYears\SemesterService;
use App\Services\SchoolSettings;
use App\Services\Timetable\TimetableCopier;
use App\Services\Timetable\TimetableValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassTimetableController extends Controller
{
    public function __construct(
        private readonly TimetableValidator $validator,
        private readonly TimetableCopier $copier,
    ) {}

    /**
     * The class timetable editor grid (spec 13 Requirement 2, admin only).
     * Past-year classes render read-only.
     */
    public function edit(Request $request, SchoolClass $schoolClass): Response
    {
        $schoolClass->load([
            'academicYear.semesters',
            'classSubjects.subject:id,name',
            'classSubjects.teacher.employee:id,name',
        ]);

        $semester = $this->resolveSemester($request, $schoolClass);

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

        $days = collect(app(SchoolSettings::class)->operationalWeekdays())
            ->map(fn (int $weekday): array => $this->dayPayload(
                $weekday,
                $this->scheduleForDay($schedules, $weekday),
                $slots,
            ))
            ->all();

        return Inertia::render('timetable/editor', [
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'class_subjects' => $schoolClass->classSubjects
                ->map(fn (ClassSubject $assignment): array => [
                    'id' => $assignment->id,
                    'subject_name' => $assignment->subject->name,
                    'teacher_name' => $assignment->teacher?->employee?->name,
                ])
                ->all(),
            'semesters' => $schoolClass->academicYear->semesters
                ->map(fn (Semester $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'number' => $option->number,
                ])
                ->all(),
            'semester_id' => $semester->id,
            'read_only' => ! $schoolClass->academicYear->is_active,
            'days' => $days,
        ]);
    }

    /**
     * Copy the class's slots from the previous semester (AC-13-07). The
     * copied grid replaces nothing: occupied positions are skipped and
     * reported in the flash toast.
     */
    public function copy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        if (! $schoolClass->academicYear?->is_active) {
            throw ValidationException::withMessages([
                'class_id' => __('timetable.errors.slot_past_year'),
            ]);
        }

        $validated = $request->validate([
            'semester_id' => ['required', 'integer', Rule::exists(Semester::class, 'id')],
        ]);

        $semester = Semester::findOrFail((int) $validated['semester_id']);
        if ($semester->academic_year_id !== $schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'semester_id' => __('timetable.errors.slot_semester_wrong_year'),
            ]);
        }

        $result = $this->copier->copy($schoolClass, $semester);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.copied', [
                'copied' => $result['copied'],
                'skipped' => count($result['skipped']),
            ]),
        ]);

        return back();
    }

    /**
     * Store a new slot in the class's timetable.
     */
    public function store(SaveSlotRequest $request, SchoolClass $schoolClass): RedirectResponse|JsonResponse
    {
        $conflictResponse = $this->checkPlacement($request, $schoolClass, null);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        TimetableSlot::create($request->slotAttributes() + ['class_id' => $schoolClass->id]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_created'),
        ]);

        return back();
    }

    /**
     * Update a slot in the class's timetable.
     */
    public function update(SaveSlotRequest $request, SchoolClass $schoolClass, TimetableSlot $slot): RedirectResponse|JsonResponse
    {
        abort_unless($slot->class_id === $schoolClass->id, 404);

        $conflictResponse = $this->checkPlacement($request, $schoolClass, $slot);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        $slot->fill($request->slotAttributes())->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_updated'),
        ]);

        return back();
    }

    /**
     * Remove a slot from the class's timetable.
     */
    public function destroy(SchoolClass $schoolClass, TimetableSlot $slot): RedirectResponse
    {
        abort_unless($slot->class_id === $schoolClass->id, 404);

        if (! $schoolClass->academicYear?->is_active) {
            abort(422, __('timetable.errors.slot_past_year'));
        }

        $slot->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_deleted'),
        ]);

        return back();
    }

    /**
     * Run the validator: hard errors throw a 422 validation error; teacher/
     * room warnings return a 409 carrying the conflict list unless the admin
     * confirmed them (AC-13-04).
     *
     * @return JsonResponse|null a 409 response when unconfirmed conflicts exist
     */
    private function checkPlacement(SaveSlotRequest $request, SchoolClass $schoolClass, ?TimetableSlot $slot): ?JsonResponse
    {
        $attributes = $request->slotAttributes();
        $semester = Semester::findOrFail($attributes['semester_id']);
        $startPeriod = LessonPeriod::findOrFail($attributes['lesson_period_id']);
        $classSubject = $attributes['class_subject_id'] !== null
            ? ClassSubject::findOrFail($attributes['class_subject_id'])
            : null;

        $errors = $this->validator->errors(
            $schoolClass,
            $semester,
            $attributes['day_of_week'],
            $startPeriod,
            $attributes['period_count'],
            $slot?->id,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['slot' => $errors]);
        }

        $conflicts = $this->validator->conflicts(
            $schoolClass,
            $semester,
            $attributes['day_of_week'],
            $startPeriod,
            $attributes['period_count'],
            $classSubject,
            $attributes['room'],
        );

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return response()->json(['conflicts' => $conflicts], 409);
        }

        return null;
    }

    /**
     * The semester being edited: the `semester` query param when it belongs
     * to the class's academic year, else the current semester, else the
     * year's first semester (past-year classes have no current semester).
     */
    private function resolveSemester(Request $request, SchoolClass $schoolClass): Semester
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
     * periods and the semester's slots on that day, with start/end times
     * derived from the periods (spec 13: times live only in the bell
     * schedule).
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

        $index = $periods->search(fn (LessonPeriod $period): bool => $period->is($slot->lessonPeriod));
        $last = $periods->slice((int) $index, $slot->period_count)->last();

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
            'start_time' => substr((string) $slot->lessonPeriod->start_time, 0, 5),
            'end_time' => $last !== null ? substr((string) $last->end_time, 0, 5) : substr((string) $slot->lessonPeriod->end_time, 0, 5),
        ];
    }
}
