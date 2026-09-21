<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * Manual record edit / day reconstruction on the exception dashboard.
 * Teachers may only touch students whose class on the record's date they
 * homeroom (spec 07 — date-effective attribution).
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

        if ($student === null) {
            return false;
        }

        // authorize() runs before validation — an unparseable date falls
        // back to the current enrollment's class instead of a 500.
        $date = $this->string('date')->toString();
        $class = Date::hasFormat($date, 'Y-m-d')
            ? $student->classOn(Date::parse($date)->toDateString())
            : $student->currentEnrollment?->schoolClass;

        return $class !== null && ClassAccess::canAccess($user, $class->id);
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
