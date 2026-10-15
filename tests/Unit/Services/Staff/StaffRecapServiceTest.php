<?php

namespace Tests\Unit\Services\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\NonSchoolDay;
use App\Models\SchoolSetting;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\SchoolSettings;
use App\Services\Staff\StaffRecapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRecapServiceTest extends TestCase
{
    use RefreshDatabase;

    private StaffRecapService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $settings = app(SchoolSettings::class);

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'staff_start_time' => '07:00:00',
            'staff_end_time' => '14:00:00',
            'school_operational_days' => [1, 2, 3, 4, 5],
        ]);
        $settings->refresh();

        $this->service = new StaffRecapService(
            new EmployeeCalendar($settings),
            $settings,
        );
    }

    /**
     * AC-16-09: 20 expected days, 17 hadir (15 present + 2 late worth 25
     * minutes), 1 dinas luar, 1 sakit, 1 alpa → rate (17 + 1) / 20 = 90%.
     */
    public function test_ac_16_09_exact_fixture_computes_90_percent_rate(): void
    {
        $employee = $this->createAc1609Fixture();

        $recap = $this->service->monthlyRecap('2026-10');

        $this->assertSame('2026-10', $recap['month']);
        $this->assertSame('2026-10-01', $recap['start_date']);
        $this->assertSame('2026-10-31', $recap['end_date']);

        $row = $this->rowFor($recap, $employee->id);

        $this->assertSame(20, $row['expected_days']);
        $this->assertSame(0, $row['extra_days']);
        $this->assertSame(17, $row['hadir']);
        $this->assertSame(2, $row['terlambat_count']);
        $this->assertSame(25, $row['terlambat_minutes']);
        $this->assertSame(1, $row['pulang_cepat_count']);
        $this->assertSame(20, $row['pulang_cepat_minutes']);
        $this->assertSame(1, $row['tidak_absen_pulang']);
        $this->assertSame(1, $row['sakit']);
        $this->assertSame(0, $row['izin']);
        $this->assertSame(0, $row['cuti']);
        $this->assertSame(1, $row['dinas_luar']);
        $this->assertSame(1, $row['alpa']);
        $this->assertSame(90.0, $row['rate']);

        // Single-employee recap: summary mirrors the row.
        $this->assertSame(1, $recap['summary']['employees']);
        $this->assertSame(20, $recap['summary']['expected_days']);
        $this->assertSame(17, $recap['summary']['hadir']);
        $this->assertSame(90.0, $recap['summary']['rate']);
    }

    public function test_tap_on_non_expected_day_counts_as_extra_day_without_raising_expected_days(): void
    {
        $employee = Employee::factory()->create(['working_days' => null]);

        // 2026-10-03 is a Saturday: the school runs Mon–Fri, so the day is
        // not expected, yet the tap must still surface as an extra day.
        $this->tap($employee, '2026-10-03', EmployeeAttendanceStatus::Present);

        $recap = $this->service->monthlyRecap('2026-10');
        $row = $this->rowFor($recap, $employee->id);

        // October 2026 has 22 Mon–Fri days.
        $this->assertSame(22, $row['expected_days']);
        $this->assertSame(1, $row['extra_days']);
        $this->assertSame(1, $row['hadir']);
        $this->assertSame(1, $recap['summary']['extra_days']);
    }

    public function test_employee_with_no_expected_days_gets_zero_rate(): void
    {
        // Saturday-only worker: never expected under Mon–Fri operations.
        $employee = Employee::factory()->create(['working_days' => [6]]);
        $this->tap($employee, '2026-10-03', EmployeeAttendanceStatus::Present);

        $row = $this->rowFor($this->service->monthlyRecap('2026-10'), $employee->id);

        $this->assertSame(0, $row['expected_days']);
        $this->assertSame(1, $row['extra_days']);
        $this->assertSame(1, $row['hadir']);
        $this->assertSame(0.0, $row['rate']);
    }

    public function test_inactive_employees_are_not_recapped(): void
    {
        $inactive = Employee::factory()->inactive()->create();
        $this->tap($inactive, '2026-10-05', EmployeeAttendanceStatus::Present);

        $recap = $this->service->monthlyRecap('2026-10');

        $this->assertSame([], $recap['rows']);
        $this->assertSame(0, $recap['summary']['employees']);
    }

    /**
     * Build the exact AC-16-09 fixture: a Mon–Fri employee whose two
     * weekday holidays leave 20 expected days in October 2026, filled
     * with 15 present, 2 late (25 min total), 1 dinas luar, 1 sick and
     * 1 absent day.
     */
    private function createAc1609Fixture(): Employee
    {
        $employee = Employee::factory()->create([
            'name' => 'Budi Santoso',
            'employee_number' => '123456789012345678',
            'working_days' => null,
        ]);

        NonSchoolDay::factory()->create(['date' => '2026-10-12', 'name' => 'Cuti Bersama']);
        NonSchoolDay::factory()->create(['date' => '2026-10-13', 'name' => 'Libur Nasional']);

        $this->tap($employee, '2026-10-05', EmployeeAttendanceStatus::Late, ['late_minutes' => 10]);
        $this->tap($employee, '2026-10-06', EmployeeAttendanceStatus::Late, ['late_minutes' => 15]);
        $this->tap($employee, '2026-10-07', EmployeeAttendanceStatus::Present, ['early_leave_minutes' => 20]);
        // A present day without checkout counts as "tidak absen pulang".
        $this->tap($employee, '2026-10-08', EmployeeAttendanceStatus::Present, ['checked_out_at' => null]);
        $this->tap($employee, '2026-10-09', EmployeeAttendanceStatus::OfficialDuty);
        $this->tap($employee, '2026-10-14', EmployeeAttendanceStatus::Sick);
        $this->tap($employee, '2026-10-15', EmployeeAttendanceStatus::Absent);

        foreach (['2026-10-01', '2026-10-02', '2026-10-16', '2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-26', '2026-10-27', '2026-10-28', '2026-10-29', '2026-10-30'] as $date) {
            $this->tap($employee, $date, EmployeeAttendanceStatus::Present);
        }

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function tap(Employee $employee, string $date, EmployeeAttendanceStatus $status, array $overrides = []): EmployeeAttendance
    {
        $scanned = in_array($status, [EmployeeAttendanceStatus::Present, EmployeeAttendanceStatus::Late], true);

        return EmployeeAttendance::factory()->create(array_merge([
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => $status,
            'checked_in_at' => $scanned ? $date.' 07:00:00' : null,
            'checked_out_at' => $scanned ? $date.' 14:00:00' : null,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'scan_method' => $scanned ? ScanMethod::Rfid : null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $recap
     * @return array<string, mixed>
     */
    private function rowFor(array $recap, int $employeeId): array
    {
        foreach ($recap['rows'] as $row) {
            if ($row['employee_id'] === $employeeId) {
                return $row;
            }
        }

        $this->fail("Recap row for employee [{$employeeId}] was not found.");
    }
}
