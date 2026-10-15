<?php

namespace App\Http\Requests\Staff;

use App\Enums\LeaveType;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreLeaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: any role,
     * as long as the account has a linked employee profile (spec 15 note;
     * spec 16 §Req 4).
     */
    public function authorize(): bool
    {
        return $this->user()?->employee !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Enum|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(LeaveType::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:'.config('attendance.leave.attachment_mimes'),
                'max:'.config('attendance.leave.attachment_max_kb'),
            ],
        ];
    }

    /**
     * Cross-field guard: the range must not overlap a pending or approved
     * leave request of the same employee (AC-16-08). Rejected and
     * cancelled ranges may be resubmitted. Overlap is deliberately
     * validation-time only — no DB uniqueness across date ranges.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Employee $employee */
                $employee = $this->user()->employee;

                $overlaps = EmployeeLeaveRequest::query()
                    ->forEmployee($employee->id)
                    ->blocking()
                    ->overlapping(
                        $this->string('start_date')->toString(),
                        $this->string('end_date')->toString(),
                    )
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('start_date', 'This range overlaps an existing pending or approved leave request.');
                }
            },
        ];
    }
}
