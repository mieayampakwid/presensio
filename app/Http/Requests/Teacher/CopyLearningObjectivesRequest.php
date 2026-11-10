<?php

namespace App\Http\Requests\Teacher;

use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Http\FormRequest;

class CopyLearningObjectivesRequest extends FormRequest
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
        ];
    }
}
