<?php

namespace App\Http\Requests\Teacher;

use App\Models\LearningObjective;
use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLearningObjectiveRequest extends FormRequest
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

        /** @var LearningObjective|null $objective */
        $objective = $this->route('learning_objective');
        if ($objective === null) {
            return false;
        }

        return ClassAccess::canManageObjectives(
            $user,
            $objective->academic_year_id,
            $objective->subject_id,
            $objective->grade_level
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var LearningObjective $objective */
        $objective = $this->route('learning_objective');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('learning_objectives')
                    ->where('academic_year_id', $objective->academic_year_id)
                    ->where('subject_id', $objective->subject_id)
                    ->where('grade_level', $objective->grade_level)
                    ->ignore($objective->id),
            ],
            'description' => ['required', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
