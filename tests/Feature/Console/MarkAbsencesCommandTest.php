<?php

namespace Tests\Feature\Console;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\NonSchoolDay;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class MarkAbsencesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_sweep_marks_record_less_students_absent(): void
    {
        // Monday 2026-09-21, 09:00 Jakarta.
        Date::setTestNow('2026-09-21 02:00:00', 'UTC');

        $swept = Student::factory()->create();
        $present = Student::factory()->create();
        $sick = Student::factory()->create();
        $alreadyAbsent = Student::factory()->create();

        Attendance::factory()->create(['student_id' => $present->id, 'date' => '2026-09-21']);
        Attendance::factory()->create(['student_id' => $sick->id, 'date' => '2026-09-21', 'status' => 'sick']);
        Attendance::factory()->absent()->create(['student_id' => $alreadyAbsent->id, 'date' => '2026-09-21']);

        $this->artisan('attendance:mark-absences')->assertSuccessful();

        $sweptRecord = $swept->refresh()->attendances->sole();
        $this->assertTrue($sweptRecord->status === AttendanceStatus::Absent);
        $this->assertNull($sweptRecord->scan_method);
        $this->assertSame('2026-09-21', $sweptRecord->date->toDateString());

        $this->assertSame(4, Attendance::count());
        $this->assertTrue($present->refresh()->attendances->sole()->status === AttendanceStatus::Present);
        $this->assertTrue($sick->refresh()->attendances->sole()->status === AttendanceStatus::Sick);
        $this->assertSame(1, $alreadyAbsent->refresh()->attendances->count());
    }

    public function test_sweep_skips_weekends(): void
    {
        // Saturday 2026-09-19.
        Date::setTestNow('2026-09-19 03:00:00', 'UTC');
        Student::factory()->create();

        $this->artisan('attendance:mark-absences')
            ->assertSuccessful()
            ->expectsOutputToContain('Non-school day');

        $this->assertSame(0, Attendance::count());
    }

    public function test_sweep_skips_non_school_days(): void
    {
        // Monday 2026-09-21 declared a holiday.
        Date::setTestNow('2026-09-21 02:00:00', 'UTC');
        NonSchoolDay::factory()->create(['date' => '2026-09-21', 'name' => 'Cuti Bersama']);
        Student::factory()->create();

        $this->artisan('attendance:mark-absences')->assertSuccessful();

        $this->assertSame(0, Attendance::count());
    }

    public function test_sweep_business_date_follows_the_school_timezone(): void
    {
        // 17:00 UTC on the 20th is already Monday the 21st in Jakarta.
        Date::setTestNow('2026-09-20 17:00:00', 'UTC');
        Student::factory()->create();

        $this->artisan('attendance:mark-absences')->assertSuccessful();

        $this->assertSame('2026-09-21', Attendance::query()->sole()->date->toDateString());
    }
}
