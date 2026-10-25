<?php

namespace App\Http\Requests\Timetable;

use App\Enums\PeriodKind;
use App\Enums\UserRole;
use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSlotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', Rule::exists(Semester::class, 'id')],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'lesson_period_id' => ['required', 'integer', Rule::exists(LessonPeriod::class, 'id')],
            'period_count' => ['required', 'integer', 'min:1', 'max:255'],
            'class_subject_id' => ['nullable', 'integer', Rule::exists(ClassSubject::class, 'id')],
            'activity_name' => ['nullable', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
            'confirm_conflicts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-field guards: active academic year, subject xor activity, the
     * subject belonging to the class, the semester belonging to the class's
     * academic year, and the span fitting the day's lesson periods.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schoolClass = $this->route('school_class');
                assert($schoolClass instanceof SchoolClass);

                if (! $schoolClass->academicYear?->is_active) {
                    $validator->errors()->add('class_id', __('timetable.errors.slot_past_year'));

                    return;
                }

                $this->validateContent($validator, $schoolClass);
                $this->validatePlacement($validator, $schoolClass);
            },
        ];
    }

    /**
     * Slot attributes ready for persistence (class_id is set by the controller).
     *
     * @return array{semester_id: int, day_of_week: int, lesson_period_id: int, period_count: int, class_subject_id: int|null, activity_name: string|null, room: string|null}
     */
    public function slotAttributes(): array
    {
        $subjectId = $this->integer('class_subject_id');
        $activity = trim((string) $this->input('activity_name', ''));
        $room = trim((string) $this->input('room', ''));

        return [
            'semester_id' => $this->integer('semester_id'),
            'day_of_week' => $this->integer('day_of_week'),
            'lesson_period_id' => $this->integer('lesson_period_id'),
            'period_count' => $this->integer('period_count'),
            'class_subject_id' => $subjectId > 0 ? $subjectId : null,
            'activity_name' => $activity !== '' ? $activity : null,
            'room' => $room !== '' ? $room : null,
        ];
    }

    /**
     * Exactly one of class_subject_id / activity_name (spec 13); the subject
     * must belong to the class being edited.
     */
    private function validateContent(Validator $validator, SchoolClass $schoolClass): void
    {
        $subjectId = $this->integer('class_subject_id');
        $hasSubject = $subjectId > 0;
        $hasActivity = trim((string) $this->input('activity_name', '')) !== '';

        if ($hasSubject === $hasActivity) {
            $validator->errors()->add('class_subject_id', __('timetable.errors.slot_subject_xor_activity'));

            return;
        }

        if ($hasSubject && ClassSubject::find($subjectId)?->class_id !== $schoolClass->id) {
            $validator->errors()->add('class_subject_id', __('timetable.errors.slot_subject_wrong_class'));
        }
    }

    /**
     * The semester must belong to the class's academic year; the starting
     * period must be a lesson of the day's bell schedule and the span must
     * fit within that day's lesson periods.
     */
    private function validatePlacement(Validator $validator, SchoolClass $schoolClass): void
    {
        $semester = Semester::find($this->integer('semester_id'));
        if ($semester !== null && $semester->academic_year_id !== $schoolClass->academic_year_id) {
            $validator->errors()->add('semester_id', __('timetable.errors.slot_semester_wrong_year'));
        }

        $startPeriod = LessonPeriod::find($this->integer('lesson_period_id'));
        if ($startPeriod === null) {
            return;
        }

        if ($startPeriod->kind !== PeriodKind::Lesson) {
            $validator->errors()->add('lesson_period_id', __('timetable.errors.slot_start_not_lesson'));

            return;
        }

        $schedule = BellSchedule::query()->get()
            ->first(fn (BellSchedule $candidate): bool => in_array(
                $this->integer('day_of_week'),
                array_map(intval(...), $candidate->weekdays),
                true,
            ));
        if ($schedule === null || $schedule->id !== $startPeriod->bell_schedule_id) {
            $validator->errors()->add('lesson_period_id', __('timetable.errors.slot_schedule_mismatch'));

            return;
        }

        $lessons = LessonPeriod::query()
            ->where('bell_schedule_id', $schedule->id)
            ->where('kind', PeriodKind::Lesson->value)
            ->pluck('number');
        $to = (int) $startPeriod->number + $this->integer('period_count');
        for ($number = (int) $startPeriod->number; $number < $to; $number++) {
            if (! $lessons->contains($number)) {
                $validator->errors()->add('period_count', __('timetable.errors.slot_span_invalid'));

                return;
            }
        }
    }
}
