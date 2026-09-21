<?php

namespace App\Http\Requests\Classes;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends FormRequest
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
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'teacher_id' => ['nullable', Rule::exists(Teacher::class, 'id')],
        ];
    }

    /**
     * Homeroom 1:1 is enforced by configuration, not schema (spec 02 §4).
     * The class being edited is excluded from the duplicate check.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (config('school.allow_multiple_homerooms')) {
                    return;
                }

                $teacherId = $this->integer('teacher_id');

                if ($teacherId === 0) {
                    return;
                }

                /** @var SchoolClass|null $target */
                $target = $this->route('school_class');

                $alreadyHomerooms = SchoolClass::query()
                    ->where('teacher_id', $teacherId)
                    // Per academic year (spec 02 v2.0).
                    ->when($target !== null, fn ($query) => $query->where('academic_year_id', $target->academic_year_id))
                    ->when($target !== null, fn ($query) => $query->whereKeyNot($target->id))
                    ->exists();

                if ($alreadyHomerooms) {
                    $validator->errors()->add(
                        'teacher_id',
                        'This teacher is already the homeroom teacher of another class.'
                    );
                }
            },
        ];
    }
}
