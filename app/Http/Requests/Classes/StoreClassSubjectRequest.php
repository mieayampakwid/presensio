<?php

namespace App\Http\Requests\Classes;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClassSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Prepare inputs for validation: default passing threshold from settings.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('passing_threshold')) {
            $this->merge([
                'passing_threshold' => app(SchoolSettings::class)->defaultPassingThreshold(),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var SchoolClass $schoolClass */
        $schoolClass = $this->route('school_class');

        return [
            'subject_id' => [
                'required',
                Rule::exists(Subject::class, 'id'),
                Rule::unique('class_subjects', 'subject_id')->where('class_id', $schoolClass->id),
            ],
            'teacher_id' => [
                'required',
                Rule::exists(Teacher::class, 'id'),
            ],
            'passing_threshold' => [
                'required',
                'numeric',
                'between:0,100',
            ],
        ];
    }

    /**
     * Additional guards: active academic year, active subject, active teacher.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var SchoolClass $schoolClass */
                $schoolClass = $this->route('school_class');

                if (! $schoolClass->academicYear?->is_active) {
                    $validator->errors()->add(
                        'class_id',
                        'Cannot modify class subjects in an inactive academic year.'
                    );
                }

                $subjectId = $this->integer('subject_id');
                if ($subjectId > 0) {
                    $subject = Subject::find($subjectId);
                    if ($subject && ! $subject->is_active) {
                        $validator->errors()->add('subject_id', 'The selected subject is inactive.');
                    }
                }

                $teacherId = $this->integer('teacher_id');
                if ($teacherId > 0) {
                    $teacher = Teacher::with('employee.user')->find($teacherId);
                    if ($teacher && ! $teacher->isActive()) {
                        $validator->errors()->add('teacher_id', 'The selected teacher is inactive.');
                    }
                }
            },
        ];
    }
}
