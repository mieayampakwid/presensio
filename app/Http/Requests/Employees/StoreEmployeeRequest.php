<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'employee_number')],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'position' => ['nullable', 'string', 'max:100'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'between:1,7'],
            'is_active' => ['required', 'boolean'],
            'is_teacher' => ['nullable', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists(User::class, 'id'), Rule::unique(Employee::class, 'user_id')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $schoolSettings = app(SchoolSettings::class);
                $operationalDays = $schoolSettings->operationalWeekdays();

                $workingDays = $this->input('working_days');
                if (is_array($workingDays) && ! empty($workingDays)) {
                    $diff = array_diff($workingDays, $operationalDays);
                    if (! empty($diff)) {
                        $validator->errors()->add('working_days', 'Working days must be a subset of school operational days.');
                    }
                }

                $userId = $this->input('user_id');
                if ($userId !== null) {
                    $targetUser = User::find($userId);
                    if ($targetUser?->hasRole(UserRole::Student)) {
                        $validator->errors()->add('user_id', 'A student account cannot be linked to an employee profile.');
                    }
                }
            },
        ];
    }
}
