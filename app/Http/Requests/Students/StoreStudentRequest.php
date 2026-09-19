<?php

namespace App\Http\Requests\Students;

use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'dob' => ['required', 'date', 'before:today'],
            'student_number' => ['nullable', 'string', 'max:255', Rule::unique(Student::class)],
            'class_id' => ['nullable', Rule::exists(SchoolClass::class, 'id')],
            'guardian_ids' => ['nullable', 'array'],
            'guardian_ids.*' => [Rule::exists(Guardian::class, 'id')],
        ];
    }

    /**
     * The student attributes, excluding the guardian pivot input.
     *
     * @return array<string, mixed>
     */
    public function studentAttributes(): array
    {
        return collect($this->safe()->except(['guardian_ids']))->all();
    }

    /**
     * The guardian ids to link, as a list of ints.
     *
     * @return list<int>
     */
    public function guardianIds(): array
    {
        return array_values(array_map(intval(...), (array) $this->input('guardian_ids', [])));
    }
}
