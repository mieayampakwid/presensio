<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Employee;
use App\Models\SchoolSetting;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

class ShiftResolverTest extends TestCase
{
    use RefreshDatabase;

    private ShiftResolver $resolver;

    private SchoolSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(SchoolSettings::class);
        $this->resolver = new ShiftResolver($this->settings);

        SchoolSetting::first()->update([
            'school_timezone' => 'Asia/Jakarta',
            'staff_start_time' => '07:00',
            'staff_end_time' => '14:00',
        ]);
        $this->settings->refresh();
    }

    public function test_roster_row_overrides_default_shift_and_unassigned_employees_resolve_to_none(): void
    {
        $pagi = Shift::factory()->create(['name' => 'Shift Pagi']);
        $siang = Shift::factory()->create(['name' => 'Shift Siang']);
        $employee = Employee::factory()->create(['shift_id' => $pagi->id]);
        ShiftRoster::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-26',
            'shift_id' => $siang->id,
        ]);

        // Roster row wins over the default shift (2026-10-26 is a Monday).
        $resolution = $this->resolver->resolve($employee, '2026-10-26');
        $this->assertTrue($resolution->shift->is($siang));
        $this->assertFalse($resolution->dayOff);

        // No roster row -> the employee's default shift.
        $resolution = $this->resolver->resolve($employee, '2026-10-27');
        $this->assertTrue($resolution->shift->is($pagi));
        $this->assertFalse($resolution->dayOff);

        // No default shift, no roster row -> no shift.
        $unassigned = Employee::factory()->create();
        $resolution = $this->resolver->resolve($unassigned, '2026-10-26');
        $this->assertNull($resolution->shift);
        $this->assertFalse($resolution->dayOff);
    }

    public function test_roster_row_with_null_shift_is_an_explicit_day_off(): void
    {
        $employee = Employee::factory()->create(['shift_id' => Shift::factory()->create()->id]);
        ShiftRoster::factory()->dayOff()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-26',
        ]);

        $resolution = $this->resolver->resolve($employee, '2026-10-26');

        $this->assertNull($resolution->shift);
        $this->assertTrue($resolution->dayOff);
    }

    public function test_end_on_returns_next_calendar_day_for_cross_midnight_shift_and_same_day_otherwise(): void
    {
        $night = Shift::factory()->night()->create();

        $this->assertSame('2026-10-26 19:00', $night->startOn('2026-10-26')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-27 07:00', $night->endOn('2026-10-26')->format('Y-m-d H:i'));

        // A CarbonInterface input resolves to the same instants.
        $this->assertSame(
            '2026-10-27 07:00',
            $night->endOn(Date::parse('2026-10-26', 'Asia/Jakarta'))->format('Y-m-d H:i'),
        );

        $day = Shift::factory()->create(['name' => 'Shift Pagi', 'start_time' => '07:00', 'end_time' => '14:00']);
        $this->assertSame('2026-10-26 14:00', $day->endOn('2026-10-26')->format('Y-m-d H:i'));
    }

    public function test_window_for_null_shift_mirrors_global_staff_times(): void
    {
        [$start, $end] = $this->resolver->windowFor(null, '2026-10-26');

        $this->assertSame('2026-10-26 07:00', $start->format('Y-m-d H:i'));
        $this->assertSame('2026-10-26 14:00', $end->format('Y-m-d H:i'));
    }

    public function test_resolve_many_matches_resolve_for_each_employee(): void
    {
        $pagi = Shift::factory()->create(['name' => 'Shift Pagi']);
        $siang = Shift::factory()->create(['name' => 'Shift Siang']);

        $rostered = Employee::factory()->create(['shift_id' => $pagi->id]);
        ShiftRoster::factory()->create([
            'employee_id' => $rostered->id,
            'date' => '2026-10-26',
            'shift_id' => $siang->id,
        ]);

        $dayOff = Employee::factory()->create(['shift_id' => $pagi->id]);
        ShiftRoster::factory()->dayOff()->create([
            'employee_id' => $dayOff->id,
            'date' => '2026-10-26',
        ]);

        $defaultOnly = Employee::factory()->create(['shift_id' => $pagi->id]);
        $unassigned = Employee::factory()->create();

        $employees = new EloquentCollection([$rostered, $dayOff, $defaultOnly, $unassigned]);
        $batched = $this->resolver->resolveMany($employees, '2026-10-26');

        $this->assertSame($employees->pluck('id')->all(), $batched->keys()->all());

        foreach ($employees as $employee) {
            $single = $this->resolver->resolve($employee, '2026-10-26');

            $this->assertSame($single->shift?->id, $batched->get($employee->id)->shift?->id);
            $this->assertSame($single->dayOff, $batched->get($employee->id)->dayOff);
        }

        // Ground a few cells so a shared wrong resolution cannot slip through.
        $this->assertTrue($batched->get($rostered->id)->shift->is($siang));
        $this->assertTrue($batched->get($dayOff->id)->dayOff);
        $this->assertTrue($batched->get($defaultOnly->id)->shift->is($pagi));
        $this->assertNull($batched->get($unassigned->id)->shift);
    }
}
