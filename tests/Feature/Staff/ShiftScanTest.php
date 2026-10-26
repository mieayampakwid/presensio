<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\RfidCard;
use App\Models\SchoolSetting;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ShiftScanTest extends TestCase
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
            'scan_debounce_minutes' => 5,
            'school_operational_days' => [1, 2, 3, 4, 5],
        ]);
        app(SchoolSettings::class)->refresh();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * @return TestResponse<Response>
     */
    private function scan(string $rfid): TestResponse
    {
        return $this->postJson(
            route('attendance.scan'),
            ['credential_type' => 'rfid', 'credential' => $rfid],
            ['X-Scanner-Key' => self::KEY],
        );
    }

    /**
     * An active employee on the 19:00-07:00 night shift with an assigned card.
     *
     * @return array{0: Employee, 1: Shift}
     */
    private function nightEmployee(string $rfid): array
    {
        $shift = Shift::factory()->night()->create();
        $employee = Employee::factory()->create(['shift_id' => $shift->id]);
        RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => $rfid]);

        return [$employee, $shift];
    }

    public function test_night_shift_checkin_at_1905_is_late_5_and_checkout_at_0650_computes_early_10(): void
    {
        [$employee, $night] = $this->nightEmployee('EMP-NIGHT-01');

        // Monday 19:05 check-in (shift start 19:00) -> late, 5 minutes
        Date::setTestNow(Date::parse('2026-10-05 19:05:00', 'Asia/Jakarta'));

        $this->scan('EMP-NIGHT-01')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-10-05', $record->date->toDateString());
        $this->assertSame(EmployeeAttendanceStatus::Late, $record->status);
        $this->assertSame(5, $record->late_minutes);
        $this->assertSame($night->id, $record->shift_id);

        // Tuesday 06:50 checkout closes the same Monday record (shift end 07:00)
        Date::setTestNow(Date::parse('2026-10-06 06:50:00', 'Asia/Jakarta'));

        $this->scan('EMP-NIGHT-01')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_out', 'detail' => 'check_out']);

        $record = $record->fresh();
        $this->assertSame('2026-10-05', $record->date->toDateString());
        $this->assertSame(10, $record->early_leave_minutes);
    }

    public function test_tuesday_0650_tap_closes_mondays_open_record_and_creates_no_tuesday_record(): void
    {
        [$employee] = $this->nightEmployee('EMP-NIGHT-02');

        Date::setTestNow(Date::parse('2026-10-05 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-02')->assertOk();

        Date::setTestNow(Date::parse('2026-10-06 06:50:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-02')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_out']);

        $this->assertSame(1, EmployeeAttendance::where('employee_id', $employee->id)->count());
        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-10-05', $record->date->toDateString());
        $this->assertNotNull($record->checked_out_at);
        $this->assertSame('2026-10-06 06:50', $record->checked_out_at->tz('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_tap_within_debounce_is_ignored_and_tuesday_1905_opens_a_new_record(): void
    {
        [$employee] = $this->nightEmployee('EMP-NIGHT-03');

        Date::setTestNow(Date::parse('2026-10-05 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-03')->assertOk();

        // Second Monday tap inside the 5-minute debounce window
        Date::setTestNow(Date::parse('2026-10-05 19:07:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-03')
            ->assertOk()
            ->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_debounce']);

        // Tuesday 19:05 is beyond Monday's shift end (07:00) -> a new Tuesday record
        Date::setTestNow(Date::parse('2026-10-06 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-03')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        $this->assertSame(2, EmployeeAttendance::where('employee_id', $employee->id)->count());
        $tuesday = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-06')->sole();
        $this->assertSame(EmployeeAttendanceStatus::Late, $tuesday->status);
        $this->assertSame(5, $tuesday->late_minutes);
    }

    public function test_changing_the_default_shift_after_creation_keeps_snapshot_and_closing_math(): void
    {
        [$employee, $night] = $this->nightEmployee('EMP-NIGHT-04');

        Date::setTestNow(Date::parse('2026-10-05 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-04')->assertOk();

        // Reassign the employee's default shift after the record was created
        $dayShift = Shift::factory()->create(['name' => 'Shift Pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $employee->update(['shift_id' => $dayShift->id]);

        // The Tuesday 06:50 checkout still closes against the snapshotted night shift
        Date::setTestNow(Date::parse('2026-10-06 06:50:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-04')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_out']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame($night->id, $record->shift_id);
        $this->assertSame(5, $record->late_minutes);
        $this->assertSame(10, $record->early_leave_minutes);
    }

    public function test_cross_midnight_close_tap_within_debounce_of_the_previous_checkin_is_ignored(): void
    {
        [$employee] = $this->nightEmployee('EMP-NIGHT-DEBOUNCE');

        // Monday 23:58 check-in; Tuesday 00:02 is inside the 5-minute debounce
        Date::setTestNow(Date::parse('2026-10-05 23:58:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-DEBOUNCE')->assertOk();

        Date::setTestNow(Date::parse('2026-10-06 00:02:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-DEBOUNCE')
            ->assertOk()
            ->assertJson(['outcome' => 'ignored', 'detail' => 'ignored_debounce']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-10-05', $record->date->toDateString());
        $this->assertNull($record->checked_out_at);
    }

    public function test_roster_row_shift_overrides_the_default_shift_for_lateness(): void
    {
        $pagi = Shift::factory()->create(['name' => 'Shift Pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $siang = Shift::factory()->create(['name' => 'Shift Siang', 'start_time' => '13:00', 'end_time' => '21:00']);
        $employee = Employee::factory()->create(['shift_id' => $pagi->id]);
        RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-ROSTER']);
        ShiftRoster::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'shift_id' => $siang->id,
        ]);

        // Monday 13:05 is 5 minutes late against the rostered Shift Siang (13:00)
        Date::setTestNow(Date::parse('2026-10-05 13:05:00', 'Asia/Jakarta'));

        $this->scan('EMP-ROSTER')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame(EmployeeAttendanceStatus::Late, $record->status);
        $this->assertSame(5, $record->late_minutes);
        $this->assertSame($siang->id, $record->shift_id);
    }

    public function test_tap_far_outside_the_shift_window_is_a_normal_checkin_for_the_tap_date(): void
    {
        [$employee] = $this->nightEmployee('EMP-NIGHT-DAY');

        // 10:00 on a 19:00-start day: a present check-in for the tap date, never rejected
        Date::setTestNow(Date::parse('2026-10-05 10:00:00', 'Asia/Jakarta'));

        $this->scan('EMP-NIGHT-DAY')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame('2026-10-05', $record->date->toDateString());
        $this->assertSame(EmployeeAttendanceStatus::Present, $record->status);
        $this->assertSame(0, $record->late_minutes);
        $this->assertSame($employee->shift_id, $record->shift_id);
    }

    public function test_unclosed_monday_night_record_does_not_steal_tuesdays_evening_tap(): void
    {
        [$employee] = $this->nightEmployee('EMP-NIGHT-CONSEC');

        Date::setTestNow(Date::parse('2026-10-05 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-CONSEC')->assertOk();

        // Tuesday's shift starts without Monday ever being closed
        Date::setTestNow(Date::parse('2026-10-06 19:05:00', 'Asia/Jakarta'));
        $this->scan('EMP-NIGHT-CONSEC')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        $monday = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-05')->sole();
        $this->assertNull($monday->checked_out_at);

        $tuesday = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-06')->sole();
        $this->assertNotNull($tuesday->checked_in_at);
    }

    public function test_employee_without_a_shift_keeps_the_global_staff_hours_math(): void
    {
        $employee = Employee::factory()->create();
        RfidCard::factory()->assignedToEmployee($employee)->create(['rfid_number' => 'EMP-NOSHIFT']);

        Date::setTestNow(Date::parse('2026-10-05 07:20:00', 'Asia/Jakarta'));
        $this->scan('EMP-NOSHIFT')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_in', 'detail' => 'check_in']);

        Date::setTestNow(Date::parse('2026-10-05 13:30:00', 'Asia/Jakarta'));
        $this->scan('EMP-NOSHIFT')
            ->assertOk()
            ->assertJson(['outcome' => 'checked_out']);

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame(EmployeeAttendanceStatus::Late, $record->status);
        $this->assertSame(20, $record->late_minutes);
        $this->assertSame(30, $record->early_leave_minutes);
        $this->assertNull($record->shift_id);
    }
}
