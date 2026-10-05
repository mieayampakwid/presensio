<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\RfidCard;
use App\Models\ScanEvent;
use App\Models\SchoolSetting;
use App\Services\Attendance\QrTokenService;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EmployeeScanTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-scanner-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.scanner_keys' => [self::KEY]]);

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'staff_start_time' => '07:00:00',
            'staff_end_time' => '14:00:00',
            'scan_debounce_minutes' => 1,
            'school_operational_days' => [1, 2, 3, 4, 5],
        ]);
        app(SchoolSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function scan(array $payload): TestResponse
    {
        return $this->postJson(route('attendance.scan'), $payload, ['X-Scanner-Key' => self::KEY]);
    }

    public function test_ac_16_02_on_time_tap_at_0650_is_present_and_late_tap_at_0720_is_late_with_20_minutes(): void
    {
        $employee1 = Employee::factory()->create(['name' => 'Pak Budi']);
        $card1 = RfidCard::factory()->assignedToEmployee($employee1)->create(['rfid_number' => 'EMP-01']);

        $employee2 = Employee::factory()->create(['name' => 'Ibu Siti']);
        $card2 = RfidCard::factory()->assignedToEmployee($employee2)->create(['rfid_number' => 'EMP-02']);

        // 1. Employee 1 taps at 06:50 Jakarta (start 07:00) -> present, late 0
        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-01'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_in',
                'detail' => 'check_in',
                'student_name' => 'Pak Budi',
                'subject_type' => 'employee',
            ]);

        $record1 = EmployeeAttendance::where('employee_id', $employee1->id)->where('date', '2026-10-05')->first();
        $this->assertNotNull($record1);
        $this->assertSame(EmployeeAttendanceStatus::Present, $record1->status);
        $this->assertSame(0, $record1->late_minutes);

        $event1 = ScanEvent::where('employee_id', $employee1->id)->first();
        $this->assertNotNull($event1);
        $this->assertSame('check_in', $event1->outcome->value);

        // 2. Employee 2 taps at 07:20 Jakarta (start 07:00) -> late, late 20 min
        Date::setTestNow(Date::parse('2026-10-05 07:20:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-02'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_in',
                'detail' => 'check_in',
                'student_name' => 'Ibu Siti',
                'subject_type' => 'employee',
            ]);

        $record2 = EmployeeAttendance::where('employee_id', $employee2->id)->where('date', '2026-10-05')->first();
        $this->assertNotNull($record2);
        $this->assertSame(EmployeeAttendanceStatus::Late, $record2->status);
        $this->assertSame(20, $record2->late_minutes);
    }

    public function test_ac_16_03_checkout_tap_at_1330_records_early_leave_30_and_tap_at_1410_resets_to_0(): void
    {
        $employee = Employee::factory()->create(['name' => 'Pak Budi']);
        $card = RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-01']);

        // Check in at 06:50
        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));
        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-01'])->assertOk();

        // Tap checkout at 13:30 (end 14:00) -> early_leave_minutes = 30
        Date::setTestNow(Date::parse('2026-10-05 13:30:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-01'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_out',
                'detail' => 'check_out',
                'subject_type' => 'employee',
            ]);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-05')->first();
        $this->assertSame(30, $record->fresh()->early_leave_minutes);

        // Later tap at 14:10 (end 14:00) -> latest tap wins, early_leave_minutes = 0
        Date::setTestNow(Date::parse('2026-10-05 14:10:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-01'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_out',
                'detail' => 'check_out',
            ]);

        $this->assertSame(0, $record->fresh()->early_leave_minutes);
    }

    public function test_ac_16_06_absent_employee_from_sweep_tapping_at_0930_is_upgraded_to_late(): void
    {
        $employee = Employee::factory()->create(['name' => 'Pak Joko']);
        $card = RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-03']);

        // Absent record pre-created by morning sweep
        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Absent,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'scan_method' => null,
        ]);

        // Taps at 09:30 Jakarta (start 07:00, late by 150 minutes)
        Date::setTestNow(Date::parse('2026-10-05 09:30:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-03'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_in',
                'detail' => 'absent_upgraded',
                'student_name' => 'Pak Joko',
                'subject_type' => 'employee',
            ]);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-05')->first();
        $this->assertSame(EmployeeAttendanceStatus::Late, $record->status);
        $this->assertSame(150, $record->late_minutes);
        $this->assertNotNull($record->checked_in_at);
    }

    public function test_ac_16_11_inactive_employee_tap_logs_ignored_inactive_and_creates_no_record(): void
    {
        $employee = Employee::factory()->inactive()->create(['name' => 'Pak Nonaktif']);
        $card = RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-INACTIVE']);

        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-INACTIVE'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'ignored',
                'detail' => 'ignored_inactive',
                'student_name' => 'Pak Nonaktif',
                'subject_type' => 'employee',
            ]);

        $this->assertSame(0, EmployeeAttendance::where('employee_id', $employee->id)->count());

        $event = ScanEvent::where('employee_id', $employee->id)->first();
        $this->assertNotNull($event);
        $this->assertSame('ignored_inactive', $event->outcome->value);
    }

    public function test_excused_employee_tap_is_ignored_excused(): void
    {
        $employee = Employee::factory()->create();
        $card = RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-SICK']);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Sick,
        ]);

        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-SICK'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'ignored',
                'detail' => 'ignored_excused',
                'subject_type' => 'employee',
            ]);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->first();
        $this->assertSame(EmployeeAttendanceStatus::Sick, $record->status);
    }

    public function test_tap_within_debounce_minutes_is_ignored_debounce(): void
    {
        $employee = Employee::factory()->create();
        $card = RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-DEBOUNCE']);

        // Check in at 06:50:00
        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));
        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-DEBOUNCE'])->assertOk();

        // Tap 30 seconds later (within 1 minute debounce)
        Date::setTestNow(Date::parse('2026-10-05 06:50:30', 'Asia/Jakarta'));

        $this->scan(['credential_type' => 'rfid', 'credential' => 'EMP-DEBOUNCE'])
            ->assertOk()
            ->assertJson([
                'outcome' => 'ignored',
                'detail' => 'ignored_debounce',
                'subject_type' => 'employee',
            ]);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->first();
        $this->assertNull($record->checked_out_at);
    }

    public function test_dynamic_qr_scan_for_employee(): void
    {
        $employee = Employee::factory()->create(['name' => 'Guru QR']);
        $tokens = new QrTokenService;

        Date::setTestNow(Date::parse('2026-10-05 06:50:00', 'Asia/Jakarta'));
        $token = $tokens->issueForEmployee($employee);

        $this->scan(['credential_type' => 'dynamic_qr', 'credential' => $token->token])
            ->assertOk()
            ->assertJson([
                'outcome' => 'checked_in',
                'detail' => 'check_in',
                'student_name' => 'Guru QR',
                'subject_type' => 'employee',
            ]);

        $this->assertDatabaseHas('employee_attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'status' => 'present',
        ]);
    }
}
