<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Employee;
use App\Models\NonSchoolDay;
use App\Models\SchoolSetting;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCalendarTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeCalendar $calendar;

    private SchoolSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(SchoolSettings::class);
        $this->calendar = new EmployeeCalendar($this->settings);

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'school_operational_days' => [1, 2, 3, 4, 5], // Monday - Friday
        ]);
        $this->settings->refresh();
    }

    public function test_employee_with_null_working_days_is_expected_on_all_operational_school_days(): void
    {
        $employee = Employee::factory()->create(['working_days' => null]);

        // 2026-10-05 is a Monday (dayOfWeekIso = 1)
        $this->assertTrue($this->calendar->isExpected($employee, '2026-10-05'));
        // 2026-10-06 is a Tuesday (2)
        $this->assertTrue($this->calendar->isExpected($employee, '2026-10-06'));
        // 2026-10-10 is a Saturday (6, not operational)
        $this->assertFalse($this->calendar->isExpected($employee, '2026-10-10'));
    }

    public function test_part_time_employee_with_explicit_working_days(): void
    {
        // Works only Monday (1) and Wednesday (3)
        $employee = Employee::factory()->create(['working_days' => [1, 3]]);

        // Monday 2026-10-05
        $this->assertTrue($this->calendar->isExpected($employee, '2026-10-05'));
        // Tuesday 2026-10-06
        $this->assertFalse($this->calendar->isExpected($employee, '2026-10-06'));
        // Wednesday 2026-10-07
        $this->assertTrue($this->calendar->isExpected($employee, '2026-10-07'));
        // Thursday 2026-10-08
        $this->assertFalse($this->calendar->isExpected($employee, '2026-10-08'));
    }

    public function test_employee_is_not_expected_on_recorded_non_school_days(): void
    {
        $employee = Employee::factory()->create(['working_days' => null]);
        NonSchoolDay::factory()->create([
            'date' => '2026-10-05',
            'name' => 'Hari Libur Nasional',
        ]);

        $this->assertFalse($this->calendar->isExpected($employee, '2026-10-05'));
    }

    public function test_expected_dates_in_range(): void
    {
        $employee = Employee::factory()->create(['working_days' => [1, 3]]);

        // 2026-10-05 (Mon) to 2026-10-09 (Fri)
        $dates = $this->calendar->expectedDatesInRange($employee, '2026-10-05', '2026-10-09');

        $this->assertSame(['2026-10-05', '2026-10-07'], $dates->all());
    }
}
