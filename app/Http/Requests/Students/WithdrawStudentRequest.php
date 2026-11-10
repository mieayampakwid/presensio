<?php

namespace App\Http\Requests\Students;

use App\Enums\UserRole;
use App\Enums\WithdrawalReason;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WithdrawStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->first();
        $startedOn = $activeEnrollment?->started_on?->toDateString() ?? '1970-01-01';
        $today = app(SchoolSettings::class)->todayDate();

        return [
            'ended_on' => [
                'required',
                'date',
                'date_format:Y-m-d',
                "after_or_equal:{$startedOn}",
                "before_or_equal:{$today}",
            ],
            'reason' => [
                'required',
                Rule::enum(WithdrawalReason::class),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'release_rfid' => ['nullable', 'boolean'],
            'deactivate_user' => ['nullable', 'boolean'],
        ];
    }

    public function endedOn(): string
    {
        return $this->string('ended_on')->toString();
    }

    public function reason(): WithdrawalReason
    {
        return WithdrawalReason::from($this->string('reason')->toString());
    }

    public function notes(): ?string
    {
        $notes = $this->string('notes')->trim()->toString();

        return $notes === '' ? null : $notes;
    }

    public function shouldReleaseRfid(): bool
    {
        return $this->boolean('release_rfid');
    }

    public function shouldDeactivateUser(): bool
    {
        return $this->boolean('deactivate_user');
    }
}
