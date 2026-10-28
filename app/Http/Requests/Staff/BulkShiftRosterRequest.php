<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Bulk roster assignment: employees × an inclusive date range, to a shift
 * or an explicit day off (spec 20 §Req 3).
 */
class BulkShiftRosterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|Rule|string>>
     */
    public function rules(): array
    {
        return [
            'employees' => ['required', 'array', 'min:1'],
            'employees.*' => ['integer', Rule::exists('employees', 'id'), 'distinct'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
        ];
    }

    /**
     * The range must start today or later and span at most 62 days.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Date::parse((string) $this->validated('start_date'));

            if ($this->validated('start_date') < app(SchoolSettings::class)->todayDate()) {
                $validator->errors()->add('start_date', __('shifts.roster.errors.past_date'));

                return;
            }

            if ($start->diffInDays(Date::parse((string) $this->validated('end_date'))) + 1 > 62) {
                $validator->errors()->add('end_date', __('shifts.roster.errors.range_too_long'));
            }
        });
    }

    /**
     * Every date in the inclusive range.
     *
     * @return list<string>
     */
    public function dates(): array
    {
        $start = Date::parse((string) $this->validated('start_date'));
        $end = Date::parse((string) $this->validated('end_date'));

        $dates = [];
        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
