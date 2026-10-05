<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

enum QrTokenError: string
{
    case InvalidFormat = 'invalid_format';
    case Expired = 'expired';
    case UnknownStudent = 'unknown_student';
    case UnknownSubject = 'unknown_subject';
}

final class QrToken
{
    public function __construct(
        public readonly string $token,
        public readonly CarbonImmutable $issuedAt,
        public readonly CarbonImmutable $expiresAt,
    ) {}
}

final class QrTokenVerification
{
    public function __construct(
        public readonly ?Student $student = null,
        public readonly ?Employee $employee = null,
        public readonly ?string $subjectType = 'student',
        public readonly ?QrTokenError $error = null,
    ) {}

    public function verified(): bool
    {
        return $this->error === null;
    }

    public function isEmployee(): bool
    {
        return $this->subjectType === 'employee';
    }

    public function isStudent(): bool
    {
        return $this->subjectType === 'student';
    }

    public function subject(): Student|Employee|null
    {
        return $this->student ?? $this->employee;
    }
}

/**
 * Server-issued, HMAC-signed dynamic QR tokens (spec 03 §Decisions
 * "Server-Issued QR Tokens" and spec 16 §Scanner integration).
 * Format:
 * - 3 segments (legacy / student default): `{student_id}.{issued_at_unix}.{hmac}`
 * - 4 segments (with subject claim): `{subject_type}.{id}.{issued_at_unix}.{hmac}`
 * Tokens are never persisted; freshness is symmetric — |now - issued| beyond
 * the TTL rejects, so screenshots shared later fail closed.
 */
class QrTokenService
{
    public function issue(Student|Employee $subject): QrToken
    {
        /** @var CarbonImmutable $now */
        $now = Date::now();

        $token = $subject instanceof Employee
            ? $this->encode('employee', $subject->getKey(), $now->getTimestamp())
            : $this->encodeStudent($subject->getKey(), $now->getTimestamp());

        return new QrToken(
            $token,
            $now,
            $now->addSeconds($this->ttl()),
        );
    }

    public function issueForEmployee(Employee $employee): QrToken
    {
        return $this->issue($employee);
    }

    public function verify(string $token): QrTokenVerification
    {
        $segments = explode('.', $token);

        if (count($segments) === 3) {
            // Legacy student format: {student_id}.{issued_at}.{hmac}
            if (! ctype_digit($segments[0]) || ! ctype_digit($segments[1])) {
                return new QrTokenVerification(null, null, 'student', QrTokenError::InvalidFormat);
            }

            [$studentId, $issuedAt] = $segments;

            if (! hash_equals($this->signatureStudent($studentId, $issuedAt), $segments[2])) {
                return new QrTokenVerification(null, null, 'student', QrTokenError::InvalidFormat);
            }

            if (abs(Date::now()->getTimestamp() - (int) $issuedAt) > $this->ttl()) {
                return new QrTokenVerification(null, null, 'student', QrTokenError::Expired);
            }

            $student = Student::query()->find((int) $studentId);

            if ($student === null) {
                return new QrTokenVerification(null, null, 'student', QrTokenError::UnknownStudent);
            }

            return new QrTokenVerification($student, null, 'student', null);
        }

        if (count($segments) === 4) {
            // Subject claim format: {subject_type}.{id}.{issued_at}.{hmac}
            [$subjectType, $id, $issuedAt, $signature] = $segments;

            if (! in_array($subjectType, ['student', 'employee'], true) || ! ctype_digit($id) || ! ctype_digit($issuedAt)) {
                return new QrTokenVerification(null, null, $subjectType, QrTokenError::InvalidFormat);
            }

            if (! hash_equals($this->signature($subjectType, $id, $issuedAt), $signature)) {
                return new QrTokenVerification(null, null, $subjectType, QrTokenError::InvalidFormat);
            }

            if (abs(Date::now()->getTimestamp() - (int) $issuedAt) > $this->ttl()) {
                return new QrTokenVerification(null, null, $subjectType, QrTokenError::Expired);
            }

            if ($subjectType === 'employee') {
                $employee = Employee::query()->find((int) $id);
                if ($employee === null) {
                    return new QrTokenVerification(null, null, 'employee', QrTokenError::UnknownSubject);
                }

                return new QrTokenVerification(null, $employee, 'employee', null);
            }

            $student = Student::query()->find((int) $id);
            if ($student === null) {
                return new QrTokenVerification(null, null, 'student', QrTokenError::UnknownStudent);
            }

            return new QrTokenVerification($student, null, 'student', null);
        }

        return new QrTokenVerification(null, null, null, QrTokenError::InvalidFormat);
    }

    private function encodeStudent(int|string $studentId, int $issuedAt): string
    {
        return "{$studentId}.{$issuedAt}.".$this->signatureStudent((string) $studentId, (string) $issuedAt);
    }

    private function encode(string $subjectType, int|string $id, int $issuedAt): string
    {
        return "{$subjectType}.{$id}.{$issuedAt}.".$this->signature($subjectType, (string) $id, (string) $issuedAt);
    }

    private function signatureStudent(string $studentId, string $issuedAt): string
    {
        return hash_hmac('sha256', "{$studentId}.{$issuedAt}", $this->secret());
    }

    private function signature(string $subjectType, string $id, string $issuedAt): string
    {
        return hash_hmac('sha256', "{$subjectType}.{$id}.{$issuedAt}", $this->secret());
    }

    private function secret(): string
    {
        return (string) (config('attendance.qr.signing_key') ?: config('app.key'));
    }

    private function ttl(): int
    {
        return (int) config('attendance.qr.ttl_seconds', 30);
    }
}
