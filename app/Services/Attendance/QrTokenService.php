<?php

namespace App\Services\Attendance;

use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

enum QrTokenError: string
{
    case InvalidFormat = 'invalid_format';
    case Expired = 'expired';
    case UnknownStudent = 'unknown_student';
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
        public readonly ?Student $student,
        public readonly ?QrTokenError $error,
    ) {}

    public function verified(): bool
    {
        return $this->error === null;
    }
}

/**
 * Server-issued, HMAC-signed dynamic QR tokens (spec 03 §Decisions
 * "Server-Issued QR Tokens"). Format: `{student_id}.{issued_at_unix}.{hmac}`.
 * Tokens are never persisted; freshness is symmetric — |now - issued| beyond
 * the TTL rejects, so screenshots shared later fail closed.
 */
class QrTokenService
{
    public function issue(Student $student): QrToken
    {
        /** @var CarbonImmutable $now */
        $now = Date::now();

        return new QrToken(
            $this->encode($student->getKey(), $now->getTimestamp()),
            $now,
            $now->addSeconds($this->ttl()),
        );
    }

    public function verify(string $token): QrTokenVerification
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3 || ! ctype_digit($segments[0]) || ! ctype_digit($segments[1])) {
            return new QrTokenVerification(null, QrTokenError::InvalidFormat);
        }

        [$studentId, $issuedAt] = $segments;

        if (! hash_equals($this->signature($studentId, $issuedAt), $segments[2])) {
            return new QrTokenVerification(null, QrTokenError::InvalidFormat);
        }

        if (abs(Date::now()->getTimestamp() - (int) $issuedAt) > $this->ttl()) {
            return new QrTokenVerification(null, QrTokenError::Expired);
        }

        $student = Student::query()->find((int) $studentId);

        if ($student === null) {
            return new QrTokenVerification(null, QrTokenError::UnknownStudent);
        }

        return new QrTokenVerification($student, null);
    }

    private function encode(int|string $studentId, int $issuedAt): string
    {
        return "{$studentId}.{$issuedAt}.".$this->signature((string) $studentId, (string) $issuedAt);
    }

    private function signature(string $studentId, string $issuedAt): string
    {
        return hash_hmac('sha256', "{$studentId}.{$issuedAt}", $this->secret());
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
