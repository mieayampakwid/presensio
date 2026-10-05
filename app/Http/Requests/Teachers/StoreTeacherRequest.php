<?php

namespace App\Http\Requests\Teachers;

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('employee_number') && ! $this->has('teacher_number')) {
            $this->merge(['teacher_number' => $this->input('employee_number')]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'teacher_number' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'employee_number')],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique(Employee::class, 'employee_number')],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'position' => ['nullable', 'string', 'max:100'],
            'working_days' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'is_teacher' => ['nullable', 'boolean'],
        ];
    }
}
