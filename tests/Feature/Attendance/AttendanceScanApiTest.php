<?php

namespace Tests\Feature\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\RfidCard;
use App\Models\ScanEvent;
use App\Models\SchoolSetting;
use App\Models\Student;
use App\Services\Attendance\QrTokenService;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AttendanceScanApiTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-scanner-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.scanner_keys' => [self::KEY]]);
        // Deterministic school clock: Monday 2026-09-21, 06:45 Jakarta —
        // before the 07:30 start, so unqualified taps check in present.
        Date::setTestNow('2026-09-20 23:45:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function scan(array $payload, ?string $key = self::KEY): TestResponse
    {
        return $this->postJson(route('attendance.scan'), $payload, $key === null ? [] : ['X-Scanner-Key' => $key]);
    }

    public function test_missing_or_wrong_key_is_unauthorized_and_logs_nothing(): void
    {
        $this->scan(['credential_type' => 'rfid', 'credential' => 'X'], null)->assertUnauthorized();
        $this->scan(['credential_type' => 'rfid', 'credential' => 'X'], 'wrong')->assertUnauthorized();

        $this->assertSame(0, ScanEvent::count());
    }

    public function test_payload_validation_rejects_unknown_credential_type(): void
    {
        $this->scan(['credential_type' => 'barcode', 'credential' => 'X'])->assertUnprocessable();
        $this->scan(['credential_type' => 'rfid'])->assertUnprocessable();
    }

    public function test_first_rfid_tap_checks_in_present_with_event_and_identifier(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_in',
                'detail' => 'check_in',
                'student_name' => $student->full_name,
            ]);

        $record = Attendance::query()->sole();
        $this->assertTrue($record->status === AttendanceStatus::Present);
        $this->assertSame('2026-09-21', $record->date->toDateString());
        $this->assertSame('rfid', $record->scan_method->value);
        $this->assertNotNull($record->checked_in_at);

        $event = ScanEvent::query()->sole();
        $this->assertSame($student->id, $event->student_id);
        $this->assertSame('001234', $event->identifier);
        $this->assertSame('check_in', $event->outcome->value);
    }

    public function test_tap_after_school_start_is_late(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        // 00:35 UTC = 07:35 Jakarta, past the 07:30 start.
        Date::setTestNow('2026-09-21 00:35:00', 'UTC');

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])->assertOk();

        $this->assertTrue(Attendance::query()->sole()->status === AttendanceStatus::Late);
    }

    public function test_second_tap_within_debounce_is_a_noop(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234']);
        Date::setTestNow('2026-09-20 23:45:30', 'UTC'); // 30s later
        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_debounce']);

        $record = Attendance::query()->sole();
        $this->assertNull($record->checked_out_at);
        $this->assertSame(2, ScanEvent::count());
    }

    public function test_checkout_tap_beyond_debounce_respects_require_checkout(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234']);
        Date::setTestNow('2026-09-21 03:45:00', 'UTC'); // 4h later

        // Default policy: checkout not required → the tap does nothing.
        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_complete']);
        $this->assertNull(Attendance::query()->sole()->checked_out_at);

        // Flip the policy; the singleton memoization must be dropped the
        // way a fresh process would re-read the row.
        SchoolSetting::query()->sole()->update(['require_checkout' => true]);
        app()->forgetInstance(SchoolSettings::class);

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson(['outcome' => 'checked_out']);
        $this->assertNotNull(Attendance::query()->sole()->refresh()->checked_out_at);

        Date::setTestNow('2026-09-21 04:45:00', 'UTC');
        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson(['detail' => 'ignored_complete']);
    }

    public function test_tap_upgrades_a_swept_absence(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);
        Attendance::factory()->absent()->create(['student_id' => $student->id, 'date' => '2026-09-21']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'absent_upgraded']);

        $record = Attendance::query()->sole();
        $this->assertTrue($record->status === AttendanceStatus::Present);
        $this->assertNotNull($record->checked_in_at);
        $this->assertSame('rfid', $record->scan_method->value);
    }

    public function test_taps_by_excused_students_are_noops(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        foreach (['sick', 'leave'] as $status) {
            Attendance::query()->delete();
            ScanEvent::query()->delete();
            Attendance::factory()->create([
                'student_id' => $student->id,
                'date' => '2026-09-21',
                'status' => $status,
            ]);

            $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])
                ->assertOk()
                ->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_excused']);

            $record = Attendance::query()->sole();
            $this->assertSame($status, $record->status->value);
            $this->assertNull($record->checked_in_at);
        }
    }

    public function test_valid_qr_token_checks_in_without_storing_the_token(): void
    {
        $student = Student::factory()->create();

        $token = (new QrTokenService)->issue($student);

        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => $token->token])
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'student_name' => $student->full_name]);

        $event = ScanEvent::query()->sole();
        $this->assertSame('dynamic_qr', $event->scan_method->value);
        $this->assertNull($event->identifier);
    }

    public function test_expired_qr_token_is_rejected_but_logged(): void
    {
        $student = Student::factory()->create();
        $token = (new QrTokenService)->issue($student);

        Date::setTestNow(Date::now()->addSeconds(31));

        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => $token->token])
            ->assertUnprocessable()
            ->assertJson(['outcome' => 'error', 'detail' => 'error_expired_token']);

        $this->assertSame(1, ScanEvent::count());
        $this->assertNull(ScanEvent::query()->sole()->student_id);
    }

    public function test_tampered_qr_token_reports_unknown_credential(): void
    {
        $student = Student::factory()->create();
        $token = (new QrTokenService)->issue($student);
        $segments = explode('.', $token->token);
        $segments[2] = str_repeat('a', 64);

        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => implode('.', $segments)])
            ->assertUnprocessable()
            ->assertJson(['detail' => 'error_unknown_credential']);
    }

    public function test_unknown_rfid_and_spare_card_error_with_logged_events(): void
    {
        RfidCard::factory()->spare()->create(['rfid_number' => '009999']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '000000'])
            ->assertUnprocessable()
            ->assertJson(['detail' => 'error_unknown_credential']);

        $this->scan(['credential_type' => 'rfid', 'credential' => '009999'])
            ->assertUnprocessable()
            ->assertJson(['detail' => 'error_unknown_credential']);

        $this->assertSame(2, ScanEvent::whereNull('student_id')->count());
    }

    public function test_trusted_device_clock_wins_for_check_in_time(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        // Device 90s behind server (within the 2-minute tolerance).
        $this->scan([
            'credential_type' => 'rfid',
            'credential' => '001234',
            'scanned_at' => '2026-09-20T23:43:30Z',
        ])->assertOk();

        $expected = Date::parse('2026-09-20T23:43:30Z')->toIso8601String();
        $this->assertSame($expected, Attendance::query()->sole()->checked_in_at->toIso8601String());
        $this->assertSame($expected, ScanEvent::query()->sole()->scanned_at->toIso8601String());
    }

    public function test_device_clock_beyond_tolerance_falls_back_to_server_time(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        $this->scan([
            'credential_type' => 'rfid',
            'credential' => '001234',
            'scanned_at' => '2026-09-20T22:45:00Z', // 60 min skew
        ])->assertOk();

        $record = Attendance::query()->sole();
        $this->assertSame(
            Date::parse('2026-09-20T23:45:00Z')->toIso8601String(),
            $record->checked_in_at->toIso8601String(),
        );
    }

    public function test_business_date_follows_the_school_timezone_across_utc_midnight(): void
    {
        $student = Student::factory()->create();
        RfidCard::factory()->assigned($student)->create(['rfid_number' => '001234']);

        // 17:05 UTC = 00:05 Jakarta on the 22nd.
        Date::setTestNow('2026-09-21 17:05:00', 'UTC');

        $this->scan(['credential_type' => 'rfid', 'credential' => '001234'])->assertOk();

        $this->assertSame('2026-09-22', Attendance::query()->sole()->date->toDateString());
    }

    public function test_replayed_qr_token_collapses_into_the_same_record(): void
    {
        $student = Student::factory()->create();
        $token = (new QrTokenService)->issue($student);

        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => $token->token]);
        Date::setTestNow(Date::now()->addSeconds(20));
        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => $token->token])
            ->assertOk()
            ->assertJson(['detail' => 'ignored_debounce']);

        $this->assertSame(1, Attendance::count());
    }

    public function test_scanner_endpoint_is_rate_limited(): void
    {
        $hit = false;
        for ($i = 0; $i < 61; $i++) {
            $response = $this->scan(['credential_type' => 'rfid', 'credential' => '000000']);
            if ($response->status() === 429) {
                $hit = true;

                break;
            }
        }

        $this->assertTrue($hit, 'expected a 429 within 61 requests');
    }
}
