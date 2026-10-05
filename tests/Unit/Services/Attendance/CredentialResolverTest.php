<?php

namespace Tests\Unit\Services\Attendance;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\Student;
use App\Services\Attendance\CredentialResolver;
use App\Services\Attendance\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CredentialResolverTest extends TestCase
{
    use RefreshDatabase;

    private CredentialResolver $resolver;

    private QrTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.qr.signing_key' => 'test-signing-secret', 'attendance.qr.ttl_seconds' => 30]);
        $this->tokens = new QrTokenService;
        $this->resolver = new CredentialResolver($this->tokens);
    }

    public function test_resolves_rfid_assigned_to_student(): void
    {
        $student = Student::factory()->create();
        $card = RfidCard::factory()->assigned($student)->create();

        $resolution = $this->resolver->resolve(ScanMethod::Rfid, $card->rfid_number);

        $this->assertFalse($resolution->isError());
        $this->assertTrue($resolution->isStudent());
        $this->assertSame($student->id, $resolution->student?->id);
        $this->assertNull($resolution->employee);
        $this->assertSame('student', $resolution->subjectType());
    }

    public function test_resolves_rfid_assigned_to_employee(): void
    {
        $employee = Employee::factory()->create();
        $card = RfidCard::factory()->assignedToEmployee($employee)->create();

        $resolution = $this->resolver->resolve(ScanMethod::Rfid, $card->rfid_number);

        $this->assertFalse($resolution->isError());
        $this->assertTrue($resolution->isEmployee());
        $this->assertSame($employee->id, $resolution->employee?->id);
        $this->assertNull($resolution->student);
        $this->assertSame('employee', $resolution->subjectType());
    }

    public function test_unassigned_or_unknown_rfid_returns_unknown_credential_error(): void
    {
        $spare = RfidCard::factory()->spare()->create();

        $spareResolution = $this->resolver->resolve(ScanMethod::Rfid, $spare->rfid_number);
        $this->assertTrue($spareResolution->isError());
        $this->assertSame(ScanOutcome::ErrorUnknownCredential, $spareResolution->error);

        $unknownResolution = $this->resolver->resolve(ScanMethod::Rfid, 'UNKNOWN_CARD');
        $this->assertTrue($unknownResolution->isError());
        $this->assertSame(ScanOutcome::ErrorUnknownCredential, $unknownResolution->error);
    }

    public function test_resolves_qr_token_for_student(): void
    {
        $student = Student::factory()->create();
        $token = $this->tokens->issue($student);

        $resolution = $this->resolver->resolve(ScanMethod::DynamicQr, $token->token);

        $this->assertFalse($resolution->isError());
        $this->assertTrue($resolution->isStudent());
        $this->assertSame($student->id, $resolution->student?->id);
    }

    public function test_resolves_qr_token_for_employee(): void
    {
        $employee = Employee::factory()->create();
        $token = $this->tokens->issueForEmployee($employee);

        $resolution = $this->resolver->resolve(ScanMethod::DynamicQr, $token->token);

        $this->assertFalse($resolution->isError());
        $this->assertTrue($resolution->isEmployee());
        $this->assertSame($employee->id, $resolution->employee?->id);
    }

    public function test_expired_qr_token_returns_expired_token_error(): void
    {
        $student = Student::factory()->create();
        $token = $this->tokens->issue($student);

        $this->travel(35)->seconds();

        $resolution = $this->resolver->resolve(ScanMethod::DynamicQr, $token->token);

        $this->assertTrue($resolution->isError());
        $this->assertSame(ScanOutcome::ErrorExpiredToken, $resolution->error);
    }
}
