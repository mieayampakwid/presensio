<?php

namespace App\Http\Requests\Classes;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassSubjectRequest extends FormRequest
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
     * Additional guards: active academic year, active teacher.
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
