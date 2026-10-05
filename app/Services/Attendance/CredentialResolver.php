<?php

namespace App\Services\Attendance;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;

final class CredentialResolution
{
    public function __construct(
        public readonly ?Student $student = null,
        public readonly ?Employee $employee = null,
        public readonly ?ScanOutcome $error = null,
    ) {}

    public function isStudent(): bool
    {
        return $this->student !== null;
    }

    public function isEmployee(): bool
    {
        return $this->employee !== null;
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }

    public function subject(): Student|Employee|null
    {
        return $this->student ?? $this->employee;
    }

    public function subjectType(): ?string
    {
        if ($this->student !== null) {
            return 'student';
        }

        if ($this->employee !== null) {
            return 'employee';
        }

        return null;
    }

    public function name(): ?string
    {
        if ($this->student !== null) {
            return $this->student->full_name;
        }

        return $this->employee?->name;
    }
}

class CredentialResolver
{
    public function __construct(
        private readonly QrTokenService $qrTokens,
    ) {}

    public function resolve(ScanMethod|string $method, string $rawCredential): CredentialResolution
    {
        $scanMethod = is_string($method) ? ScanMethod::tryFrom($method) : $method;

        if ($scanMethod === ScanMethod::Rfid) {
            return $this->resolveRfid($rawCredential);
        }

        if ($scanMethod === ScanMethod::DynamicQr) {
            return $this->resolveQr($rawCredential);
        }

        return new CredentialResolution(null, null, ScanOutcome::ErrorUnknownCredential);
    }

    private function resolveRfid(string $rfidNumber): CredentialResolution
    {
        $card = RfidCard::query()
            ->with(['student', 'employee'])
            ->where('rfid_number', $rfidNumber)
            ->first();

        if ($card === null) {
            return new CredentialResolution(null, null, ScanOutcome::ErrorUnknownCredential);
        }

        if ($card->student !== null) {
            return new CredentialResolution($card->student, null, null);
        }

        if ($card->employee !== null) {
            return new CredentialResolution(null, $card->employee, null);
        }

        return new CredentialResolution(null, null, ScanOutcome::ErrorUnknownCredential);
    }

    private function resolveQr(string $qrToken): CredentialResolution
    {
        $verification = $this->qrTokens->verify($qrToken);

        if (! $verification->verified()) {
            $outcome = match ($verification->error) {
                QrTokenError::Expired => ScanOutcome::ErrorExpiredToken,
                default => ScanOutcome::ErrorUnknownCredential,
            };

            return new CredentialResolution(null, null, $outcome);
        }

        if ($verification->isEmployee() && $verification->employee !== null) {
            return new CredentialResolution(null, $verification->employee, null);
        }

        if ($verification->student !== null) {
            return new CredentialResolution($verification->student, null, null);
        }

        return new CredentialResolution(null, null, ScanOutcome::ErrorUnknownCredential);
    }
}
