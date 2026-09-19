<?php

namespace App\Services\Attendance;

use App\Enums\ScanOutcome;
use App\Models\Attendance;
use App\Models\Student;

final class ScanResult
{
    public function __construct(
        public readonly ScanOutcome $outcome,
        public readonly ?Student $student,
        public readonly ?Attendance $attendance,
    ) {}

    public function isError(): bool
    {
        return $this->outcome === ScanOutcome::ErrorExpiredToken
            || $this->outcome === ScanOutcome::ErrorUnknownCredential;
    }

    /**
     * Coarse response bucket for the scanner display.
     */
    public function coarse(): string
    {
        return match ($this->outcome) {
            ScanOutcome::CheckIn, ScanOutcome::AbsentUpgraded => 'checked_in',
            ScanOutcome::CheckOut => 'checked_out',
            ScanOutcome::IgnoredDebounce, ScanOutcome::IgnoredComplete, ScanOutcome::IgnoredExcused => 'ignored',
            ScanOutcome::ErrorExpiredToken, ScanOutcome::ErrorUnknownCredential => 'error',
        };
    }
}
