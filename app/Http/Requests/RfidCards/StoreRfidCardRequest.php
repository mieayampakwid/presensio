<?php

namespace App\Http\Requests\RfidCards;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfidCardRequest extends FormRequest
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
            'rfid_number' => ['required', 'string', 'max:255', Rule::unique(RfidCard::class)],
            'student_id' => ['nullable', Rule::exists(Student::class, 'id')],
            'employee_id' => ['nullable', Rule::exists(Employee::class, 'id')],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('student_id') && $this->filled('employee_id')) {
                    $validator->errors()->add('employee_id', 'An RFID card cannot be assigned to both a student and an employee.');
                }
            },
        ];
    }
}
