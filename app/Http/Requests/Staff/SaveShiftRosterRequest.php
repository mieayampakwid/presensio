<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One roster cell: set (shift or explicit day off) or clear. The clear
 * action reuses the same shape minus shift_id.
 */
class SaveShiftRosterRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
        ];
    }

    /**
     * Only today and future dates are editable (spec 20 §Req 3).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->validated('date') < app(SchoolSettings::class)->todayDate()) {
                $validator->errors()->add('date', __('shifts.roster.errors.past_date'));
            }
        });
    }
}
