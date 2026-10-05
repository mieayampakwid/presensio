<?php

namespace App\Http\Requests\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertStaffAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Manual overrides are restricted to admins only (spec 16 §Decisions, AC-16-10).
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
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(EmployeeAttendanceStatus::class)],
            'checked_in_at' => ['nullable', 'string'],
            'checked_out_at' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
