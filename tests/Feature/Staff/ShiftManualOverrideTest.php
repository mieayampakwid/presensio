<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SchoolSetting;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ShiftManualOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'staff_start_time' => '07:00:00',
            'staff_end_time' => '14:00:00',
            'school_operational_days' => [1, 2, 3, 4, 5],
        ]);
        app(SchoolSettings::class)->refresh();
    }

    /**
     * @param  array<string, mixed>  $times
     * @return TestResponse<Response>
     */
    private function override(Employee $employee, array $times): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->put(route('staff-attendance.upsert'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-05',
                'status' => EmployeeAttendanceStatus::Present->value,
                ...$times,
            ]);
    }

    public function test_cross_midnight_override_parses_the_checkout_as_next_day(): void
    {
        $shift = Shift::factory()->night()->create();
        $employee = Employee::factory()->create(['shift_id' => $shift->id]);

        // Checkout 06:50 precedes check-in 19:05 -> next calendar day on a 19:00-07:00 shift
        $this->override($employee, ['checked_in_at' => '19:05', 'checked_out_at' => '06:50'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff-attendance.index', ['date' => '2026-10-05']));

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame($shift->id, $record->shift_id);
        $this->assertSame(5, $record->late_minutes);
        $this->assertSame(10, $record->early_leave_minutes);
        $this->assertSame('2026-10-05 19:05', $record->checked_in_at->tz('Asia/Jakarta')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 06:50', $record->checked_out_at->tz('Asia/Jakarta')->format('Y-m-d H:i'));
    }

    public function test_override_succeeds_on_an_explicit_day_off_roster_date(): void
    {
        $shift = Shift::factory()->night()->create();
        $employee = Employee::factory()->create(['shift_id' => $shift->id]);
        ShiftRoster::factory()->dayOff()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
        ]);

        // Admin judgment beats the roster: the day-off date can still be overridden
        $this->override($employee, ['checked_in_at' => '07:15', 'checked_out_at' => '14:30'])
            ->assertSessionHasNoErrors();

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertSame(15, $record->late_minutes);
        $this->assertSame(0, $record->early_leave_minutes);
        $this->assertNull($record->shift_id); // day-off resolution -> global staff times
    }

    public function test_override_for_an_employee_without_a_shift_keeps_the_global_math(): void
    {
        $employee = Employee::factory()->create();

        $this->override($employee, ['checked_in_at' => '07:15', 'checked_out_at' => '13:30'])
            ->assertSessionHasNoErrors();

        $record = EmployeeAttendance::where('employee_id', $employee->id)->sole();
        $this->assertNull($record->shift_id);
        $this->assertSame(15, $record->late_minutes);
        $this->assertSame(30, $record->early_leave_minutes);
    }
}
