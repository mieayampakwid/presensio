<?php

namespace App\Http\Requests\Teacher;

use App\Models\SchoolClass;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishReportCardRequest extends FormRequest
{
    /**
     * Only the class's homeroom teacher and admins may publish.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        /** @var SchoolClass|null $schoolClass */
        $schoolClass = $this->route('school_class');

        return $schoolClass !== null && ClassAccess::canWrite($user, $schoolClass->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ];
    }

    /**
     * The semester must belong to the class's academic year.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        /** @var SchoolClass|null $schoolClass */
        $schoolClass = $this->route('school_class');

        return [
            function ($validator) use ($schoolClass): void {
                if ($schoolClass === null) {
                    return;
                }

                /** @var Semester|null $semester */
                $semester = Semester::find($this->integer('semester_id'));

                if ($semester === null || $semester->academic_year_id !== $schoolClass->academic_year_id) {
                    $validator->errors()->add('semester_id', 'Semester tidak termasuk tahun ajaran kelas ini.');
                }
            },
        ];
    }
}
