<?php

namespace Tests\Feature\Attendance;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\ScanEvent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ScannerScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-09-20 23:45:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_student_check_in_uses_server_clock_and_authoritative_status(): void
    {
        $student = Student::factory()->enrolledIn(SchoolClass::factory()->create())->create();
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        $token = app(QrTokenService::class)->issue($student)->token;
        $this->actingAs($operator)->postJson('/scanner/scan', ['credential' => $token, 'scanned_at' => '2026-09-21T12:00:00Z', 'credential_type' => 'rfid'])
            ->assertOk()->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in', 'student_name' => $student->full_name,
                'subject_type' => 'student', 'attendance_status' => 'present']);
        $this->assertSame('2026-09-20 23:45:00', Attendance::sole()->checked_in_at->format('Y-m-d H:i:s'));
        $event = ScanEvent::sole();
        $this->assertSame('dynamic_qr', $event->scan_method->value);
        $this->assertNull($event->identifier);
    }

    public function test_absent_upgrade_can_be_present_and_represented_scan_is_debounced(): void
    {
        $student = Student::factory()->enrolledIn(SchoolClass::factory()->create())->create();
        Attendance::factory()->absent()->create(['student_id' => $student->id, 'date' => '2026-09-21']);
        $token = app(QrTokenService::class)->issue($student)->token;
        $this->actingAs(User::factory()->teacher()->create())->postJson('/scanner/scan', ['credential' => $token])
            ->assertOk()->assertJson(['detail' => 'absent_upgraded', 'attendance_status' => 'present']);
        $this->postJson('/scanner/scan', ['credential' => $token])->assertOk()->assertJson(['detail' => 'ignored_debounce']);
        $this->assertDatabaseCount('scan_events', 2);
        $this->assertNull(Attendance::sole()->checked_out_at);
    }

    public function test_late_and_employee_results_are_derived_from_the_engine(): void
    {
        Date::setTestNow('2026-09-21 03:00:00', 'UTC');
        $student = Student::factory()->enrolledIn(SchoolClass::factory()->create())->create();
        $employee = Employee::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->postJson('/scanner/scan', ['credential' => app(QrTokenService::class)->issue($student)->token])
            ->assertOk()->assertJson(['attendance_status' => 'late']);
        $this->postJson('/scanner/scan', ['credential' => app(QrTokenService::class)->issue($employee)->token])
            ->assertOk()->assertJson(['subject_type' => 'employee', 'student_name' => $employee->name, 'attendance_status' => 'late']);
        $this->assertDatabaseCount('scan_events', 2);
        $this->assertDatabaseHas('employee_attendances', ['employee_id' => $employee->id, 'status' => 'late']);
    }

    public function test_alumni_and_inactive_employee_are_audited_without_attendance(): void
    {
        $student = Student::factory()->create();
        $employee = Employee::factory()->create(['is_active' => false]);
        $this->actingAs(User::factory()->teacher()->create())->postJson('/scanner/scan', ['credential' => app(QrTokenService::class)->issue($student)->token])
            ->assertOk()->assertJson(['detail' => 'ignored_alumni', 'attendance_status' => null]);
        $this->postJson('/scanner/scan', ['credential' => app(QrTokenService::class)->issue($employee)->token])
            ->assertOk()->assertJson(['detail' => 'ignored_inactive', 'attendance_status' => null]);
        $this->assertDatabaseCount('scan_events', 2);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertDatabaseCount('employee_attendances', 0);
    }

    public function test_credential_errors_produce_one_event_each_and_no_identity(): void
    {
        $student = Student::factory()->create();
        $token = app(QrTokenService::class)->issue($student)->token;
        Date::setTestNow('2026-09-20 23:45:31', 'UTC');
        $this->actingAs(User::factory()->teacher()->create())->postJson('/scanner/scan', ['credential' => $token])
            ->assertUnprocessable()->assertExactJson(['outcome' => 'error', 'detail' => 'error_expired_token']);
        $this->postJson('/scanner/scan', ['credential' => 'invalid'])->assertUnprocessable()
            ->assertExactJson(['outcome' => 'error', 'detail' => 'error_unknown_credential']);
        $this->assertDatabaseCount('scan_events', 2);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_validation_and_authorization_rejections_do_not_enter_engine(): void
    {
        $this->actingAs(User::factory()->teacher()->create());
        foreach (['', '   ', str_repeat('a', 256), ['token']] as $credential) {
            $this->postJson('/scanner/scan', ['credential' => $credential])->assertUnprocessable()->assertJsonValidationErrors('credential');
        }
        $this->actingAs(User::factory()->parent()->create())->postJson('/scanner/scan', ['credential' => 'invalid'])->assertForbidden();
        $this->assertDatabaseCount('scan_events', 0);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_throttle_is_per_user_and_rejected_requests_create_no_event(): void
    {
        $first = User::factory()->teacher()->create();
        $this->actingAs($first);
        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/scanner/scan', ['credential' => 'invalid'])->assertUnprocessable();
        }
        $this->postJson('/scanner/scan', ['credential' => 'invalid'])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('scan_events', 60);
        $this->actingAs(User::factory()->teacher()->create())->postJson('/scanner/scan', ['credential' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('scan_events', 61);
    }
}
