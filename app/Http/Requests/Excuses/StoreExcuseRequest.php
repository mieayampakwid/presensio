<?php

namespace App\Http\Requests\Excuses;

use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use App\Enums\UserRole;
use App\Models\Excuse;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreExcuseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: a parent
     * with a linked guardian profile.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Parent
            && $this->user()->guardian !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Enum|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', Rule::exists(Student::class, 'id')],
            'type' => ['required', new Enum(ExcuseType::class)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:'.config('attendance.excuses.attachment_mimes'),
                'max:'.config('attendance.excuses.attachment_max_kb'),
            ],
        ];
    }

    /**
     * Cross-field guards: the excuse must cover one of this guardian's own
     * children, and its range must not overlap a pending or approved
     * excuse for the same child (rejected ranges may be resubmitted).
     * Overlap is deliberately validation-time only — no DB uniqueness
     * across date ranges.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $studentId = $this->integer('student_id');

                if ($this->user()->guardian->students()->whereKey($studentId)->doesntExist()) {
                    $validator->errors()->add('student_id', 'The selected child is not linked to your account.');
                }
            },
            function (Validator $validator): void {
                $overlaps = Excuse::query()
                    ->where('student_id', $this->integer('student_id'))
                    ->whereIn('status', [ExcuseStatus::Pending->value, ExcuseStatus::Approved->value])
                    ->where('start_date', '<=', $this->string('end_date')->toString())
                    ->where('end_date', '>=', $this->string('start_date')->toString())
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('start_date', 'This range overlaps an existing pending or approved excuse.');
                }
            },
        ];
    }
}
