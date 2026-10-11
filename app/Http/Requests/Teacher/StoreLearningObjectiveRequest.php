<?php

namespace App\Http\Requests\Teacher;

use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearningObjectiveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return ClassAccess::canManageObjectives(
            $user,
            (int) $this->input('academic_year_id'),
            (int) $this->input('subject_id'),
            (int) $this->input('grade_level')
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade_level' => ['required', 'integer', 'min:1', 'max:12'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('learning_objectives')
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('subject_id', $this->input('subject_id'))
                    ->where('grade_level', $this->input('grade_level')),
            ],
            'description' => ['required', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
