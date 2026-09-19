<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Student;
use App\Services\Attendance\QrCodeRenderer;
use App\Services\Attendance\QrTokenError;
use App\Services\Attendance\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class QrTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private QrTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.qr.signing_key' => 'test-signing-secret', 'attendance.qr.ttl_seconds' => 30]);
        $this->service = new QrTokenService;
    }

    public function test_issued_token_verifies_back_to_its_student(): void
    {
        $student = Student::factory()->create();

        $token = $this->service->issue($student);
        $verification = $this->service->verify($token->token);

        $this->assertTrue($verification->verified());
        $this->assertTrue($student->is($verification->student));
        $this->assertTrue($token->expiresAt->greaterThan($token->issuedAt));
    }

    public function test_token_older_than_ttl_is_expired(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        Date::setTestNow(Date::now()->addSeconds(31));
        $verification = $this->service->verify($token->token);
        Date::setTestNow();

        $this->assertSame(QrTokenError::Expired, $verification->error);
    }

    public function test_future_issued_token_is_expired_by_the_symmetric_window(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        Date::setTestNow(Date::now()->subSeconds(31));
        $verification = $this->service->verify($token->token);
        Date::setTestNow();

        $this->assertSame(QrTokenError::Expired, $verification->error);
    }

    public function test_within_ttl_on_both_sides_still_verifies(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        Date::setTestNow(Date::now()->addSeconds(30));
        $verification = $this->service->verify($token->token);
        Date::setTestNow();

        $this->assertTrue($verification->verified());
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        $segments = explode('.', $token->token);
        $segments[2] = str_repeat('0', 64);

        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify(implode('.', $segments))->error);
    }

    public function test_tampered_student_id_is_rejected(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        $segments = explode('.', $token->token);
        $segments[0] = (string) ($student->id + 999);

        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify(implode('.', $segments))->error);
    }

    public function test_malformed_tokens_are_invalid_format(): void
    {
        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify('garbage')->error);
        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify('1.2.3.4')->error);
        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify('abc.123.abc')->error);
    }

    public function test_token_for_a_deleted_student_is_unknown(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);
        $student->delete();

        $this->assertSame(QrTokenError::UnknownStudent, $this->service->verify($token->token)->error);
    }

    public function test_signing_key_isolation_between_configs(): void
    {
        $student = Student::factory()->create();
        $token = $this->service->issue($student);

        config(['attendance.qr.signing_key' => 'another-secret']);

        $this->assertSame(QrTokenError::InvalidFormat, $this->service->verify($token->token)->error);
    }

    public function test_renderer_produces_inline_svg_without_prolog(): void
    {
        $svg = (new QrCodeRenderer)->svg('payload-token');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
    }
}
