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

class UpdateRfidCardRequest extends FormRequest
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
        $card = $this->route('rfid_card');

        return [
            'rfid_number' => ['required', 'string', 'max:255', Rule::unique(RfidCard::class)->ignore($card)],
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
                /** @var RfidCard|null $card */
                $card = $this->route('rfid_card');

                $submittedStudent = $this->has('student_id') ? $this->input('student_id') : ($card?->student_id);
                $submittedEmployee = $this->has('employee_id') ? $this->input('employee_id') : ($card?->employee_id);

                if (! empty($submittedStudent) && ! empty($submittedEmployee)) {
                    $validator->errors()->add('employee_id', 'An RFID card cannot be assigned to both a student and an employee.');
                }
            },
        ];
    }
}
