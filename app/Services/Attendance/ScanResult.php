<?php

namespace App\Services\Attendance;

use App\Enums\ScanOutcome;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\Student;

final class ScanResult
{
    public function __construct(
        public readonly ScanOutcome $outcome,
        public readonly ?Student $student = null,
        public readonly ?Attendance $attendance = null,
        public readonly ?Employee $employee = null,
        public readonly ?EmployeeAttendance $employeeAttendance = null,
    ) {}

    public function isError(): bool
    {
        return $this->outcome === ScanOutcome::ErrorExpiredToken
            || $this->outcome === ScanOutcome::ErrorUnknownCredential;
    }

    public function isEmployee(): bool
    {
        return $this->employee !== null;
    }

    public function subjectName(): ?string
    {
        return $this->student?->full_name ?? $this->employee?->name;
    }

    /**
     * Coarse response bucket for the scanner display.
     */
    public function coarse(): string
    {
        return match ($this->outcome) {
            ScanOutcome::CheckIn, ScanOutcome::AbsentUpgraded => 'checked_in',
            ScanOutcome::CheckOut => 'checked_out',
            ScanOutcome::IgnoredDebounce,
            ScanOutcome::IgnoredComplete,
            ScanOutcome::IgnoredExcused,
            ScanOutcome::IgnoredInactive => 'ignored',
            ScanOutcome::ErrorExpiredToken, ScanOutcome::ErrorUnknownCredential => 'error',
        };
    }
}
