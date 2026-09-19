<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Manual record edit / day reconstruction on the exception dashboard.
 * Teachers may only touch students whose current class they homeroom.
 */
class UpsertAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! in_array($user->role, [UserRole::Admin, UserRole::Teacher], true)) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        $student = Student::query()->find($this->integer('student_id'));

        return $student !== null
            && $student->class_id !== null
            && ClassAccess::canAccess($user, $student->class_id);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', Rule::exists(Student::class, 'id')],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'checked_in_at' => ['nullable', 'date_format:H:i'],
            'checked_out_at' => ['nullable', 'date_format:H:i', 'after_or_equal:checked_in_at'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
