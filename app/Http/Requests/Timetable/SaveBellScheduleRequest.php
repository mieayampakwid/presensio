<?php

namespace App\Http\Requests\Timetable;

use App\Enums\PeriodKind;
use App\Enums\UserRole;
use App\Models\BellSchedule;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveBellScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Prepare inputs for validation: normalize weekdays and period rows.
     */
    protected function prepareForValidation(): void
    {
        $weekdays = array_map(
            fn ($day): int => (int) $day,
            (array) $this->input('weekdays', []),
        );

        $periods = array_map(
            fn (array $period): array => [
                'id' => isset($period['id']) && $period['id'] !== '' ? (int) $period['id'] : null,
                'kind' => (string) ($period['kind'] ?? ''),
                'number' => isset($period['number']) && $period['number'] !== '' ? (int) $period['number'] : null,
                'name' => isset($period['name']) && trim((string) $period['name']) !== '' ? trim((string) $period['name']) : null,
                'start_time' => (string) ($period['start_time'] ?? ''),
                'end_time' => (string) ($period['end_time'] ?? ''),
            ],
            (array) $this->input('periods', []),
        );

        $this->merge(['weekdays' => $weekdays, 'periods' => $periods]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var BellSchedule|null $schedule */
        $schedule = $this->route('bell_schedule');
        $scheduleId = $schedule instanceof BellSchedule ? $schedule->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique(BellSchedule::class, 'name')->ignore($scheduleId),
            ],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.id' => [
                'nullable',
                'integer',
                Rule::exists('lesson_periods', 'id')->where('bell_schedule_id', $scheduleId ?? 0),
            ],
            'periods.*.kind' => ['required', Rule::enum(PeriodKind::class)],
            'periods.*.number' => ['nullable', 'integer', 'min:1', 'max:255'],
            'periods.*.name' => ['nullable', 'string', 'max:50'],
            'periods.*.start_time' => ['required', 'date_format:H:i'],
            'periods.*.end_time' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validatePeriodRows($validator);
            $this->validateCoverage($validator);
        });
    }

    /**
     * Schedule attributes ready for persistence.
     *
     * @return array{name: string, weekdays: list<int>}
     */
    public function scheduleAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'weekdays' => array_values((array) $this->validated('weekdays')),
        ];
    }

    /**
     * Ordered period rows ready for persistence (payload order = sort order).
     *
     * @return list<array{id: int|null, kind: string, number: int|null, name: string|null, start_time: string, end_time: string}>
     */
    public function periodRows(): array
    {
        return array_values((array) $this->validated('periods'));
    }

    /**
     * Within-schedule rules: kind-specific fields, start < end, no overlap,
     * sequential lesson numbers (plan 13 Task 1 Step 1).
     */
    private function validatePeriodRows(Validator $validator): void
    {
        $rows = $this->periodRows();
        $lessonNumbers = [];
        $previousEnd = null;

        foreach ($rows as $index => $row) {
            $isLesson = $row['kind'] === PeriodKind::Lesson->value;

            if ($isLesson && $row['number'] === null) {
                $validator->errors()->add("periods.{$index}.number", __('timetable.errors.period_number_required'));
            }

            if ($isLesson && $row['name'] !== null) {
                $validator->errors()->add("periods.{$index}.name", __('timetable.errors.period_name_not_allowed'));
            }

            if (! $isLesson && $row['name'] === null) {
                $validator->errors()->add("periods.{$index}.name", __('timetable.errors.period_name_required'));
            }

            if (! $isLesson && $row['number'] !== null) {
                $validator->errors()->add("periods.{$index}.number", __('timetable.errors.period_number_not_allowed'));
            }

            if ($row['start_time'] >= $row['end_time']) {
                $validator->errors()->add("periods.{$index}.end_time", __('timetable.errors.period_end_after_start'));

                continue;
            }

            if ($previousEnd !== null && $row['start_time'] < $previousEnd) {
                $validator->errors()->add('periods', __('timetable.errors.periods_overlap'));

                continue;
            }

            $previousEnd = $row['end_time'];

            if ($isLesson) {
                $lessonNumbers[] = (int) $row['number'];
            }
        }

        foreach ($lessonNumbers as $position => $number) {
            if ($number !== $position + 1) {
                $validator->errors()->add('periods', __('timetable.errors.lesson_numbers_sequential'));

                return;
            }
        }
    }

    /**
     * Across all schedules the union of weekdays must equal the operational
     * weekdays exactly (AC-13-01): no day uncovered, none covered twice, none
     * non-operational.
     */
    private function validateCoverage(Validator $validator): void
    {
        /** @var BellSchedule|null $schedule */
        $schedule = $this->route('bell_schedule');

        $operational = collect(app(SchoolSettings::class)->operationalWeekdays());
        $weekdays = collect(array_map(
            fn ($day): int => (int) $day,
            (array) $this->input('weekdays', []),
        ));
        $others = BellSchedule::query()
            ->whereKeyNot($schedule?->id)
            ->get('weekdays')
            ->pluck('weekdays')
            ->flatten()
            ->map(fn ($day): int => (int) $day)
            ->unique()
            ->values();

        $nonOperational = $weekdays->diff($operational);
        if ($nonOperational->isNotEmpty()) {
            $validator->errors()->add('weekdays', __('timetable.errors.weekdays_non_operational', [
                'days' => $this->weekdayLabels($nonOperational),
            ]));
        }

        $taken = $weekdays->intersect($others);
        if ($taken->isNotEmpty()) {
            $validator->errors()->add('weekdays', __('timetable.errors.weekdays_taken', [
                'days' => $this->weekdayLabels($taken),
            ]));
        }

        $uncovered = $operational->diff($weekdays->merge($others));
        if ($uncovered->isNotEmpty()) {
            $validator->errors()->add('weekdays', __('timetable.errors.weekdays_uncovered', [
                'days' => $this->weekdayLabels($uncovered),
            ]));
        }
    }

    /**
     * Localized weekday names for validation messages.
     *
     * @param  Collection<int, int>  $weekdays
     */
    private function weekdayLabels(Collection $weekdays): string
    {
        return $weekdays
            ->map(fn (int $day): string => __('timetable.weekdays.'.$day))
            ->implode(', ');
    }
}
