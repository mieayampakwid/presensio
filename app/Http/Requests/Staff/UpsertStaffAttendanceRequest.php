<?php

namespace App\Http\Requests\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Attendance\ShiftResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class UpsertStaffAttendanceRequest extends FormRequest
{
    public function __construct(private readonly ShiftResolver $shifts)
    {
        parent::__construct();
    }

    /**
     * Determine if the user is authorized to make this request.
     * Manual overrides are restricted to admins only (spec 16 §Decisions, AC-16-10).
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * The effective shift of the overridden (employee, date), or null when
     * the payload cannot resolve yet (invalid id/date — the strict same-day
     * checkout rule then applies) or no shift is assigned.
     */
    public function effectiveShift(): ?Shift
    {
        $date = $this->string('date')->toString();

        if ($date === '' || ! Date::hasFormat($date, 'Y-m-d')) {
            return null;
        }

        $employee = Employee::find($this->integer('employee_id'));

        if ($employee === null) {
            return null;
        }

        return $this->shifts->resolve($employee, $date)->shift;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        // On a cross-midnight shift a checkout time earlier than the
        // check-in legitimately parses as the next day (spec 20 §Req 8).
        $crossesMidnight = $this->effectiveShift()?->crosses_midnight === true;

        return [
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(EmployeeAttendanceStatus::class)],
            'checked_in_at' => ['nullable', 'date_format:H:i'],
            'checked_out_at' => [
                'nullable',
                'date_format:H:i',
                ...($crossesMidnight ? [] : ['after_or_equal:checked_in_at']),
            ],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
