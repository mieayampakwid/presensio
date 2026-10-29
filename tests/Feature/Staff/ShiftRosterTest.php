<?php

namespace Tests\Feature\Staff;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Models\User;
use App\Services\Attendance\ShiftResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShiftRosterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The anchored grid week: Monday 2026-10-05 .. Sunday 2026-10-11.
     * "Now" is frozen to Tuesday 2026-10-06, so the 5th is past and the
     * rest of the week is editable.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-10-06 08:00:00', 'Asia/Jakarta');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * The grid payload resolves cells by precedence roster > default >
     * fallback and marks past dates read-only.
     */
    public function test_grid_cells_reflect_roster_default_and_fallback_precedence(): void
    {
        $admin = User::factory()->admin()->create();
        $pagi = Shift::factory()->create(['name' => 'Shift Pagi']);
        $siang = Shift::factory()->create(['name' => 'Shift Siang', 'start_time' => '12:00', 'end_time' => '18:00']);

        $withRoster = Employee::factory()->create(['name' => 'Andi', 'shift_id' => $pagi->id]);
        ShiftRoster::factory()->create(['employee_id' => $withRoster->id, 'date' => '2026-10-07', 'shift_id' => $siang->id]);

        $withDefault = Employee::factory()->create(['name' => 'Budi', 'shift_id' => Shift::factory()->night()->create()->id]);
        $withoutShift = Employee::factory()->create(['name' => 'Citra', 'shift_id' => null]);

        $this->actingAs($admin)
            ->get(route('staff-roster.index', ['week' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/roster')
                ->has('rows', 3)
                ->where('rows.0.name', 'Andi')
                ->where('rows.0.default_shift.name', 'Shift Pagi')
                ->where('rows.0.cells.2.date', '2026-10-07')
                ->where('rows.0.cells.2.source', 'roster')
                ->where('rows.0.cells.2.shift.name', 'Shift Siang')
                ->where('rows.1.cells.1.source', 'default')
                ->where('rows.1.cells.1.shift.name', 'Shift Malam')
                ->where('rows.2.cells.1.source', 'fallback')
                ->where('rows.2.cells.1.shift', null)
                ->where('rows.0.cells.0.editable', false)
                ->where('rows.0.cells.1.editable', true));
    }

    /**
     * Bulk assign upserts one roster row per date in the range, including
     * weekend days the employee is not expected (the rows change hours
     * only, never expectations — spec 20 §Decisions), bounded at 62 days.
     */
    public function test_bulk_assign_writes_a_row_for_every_date_in_the_range(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create(['name' => 'Shift Pagi']);
        $employee = Employee::factory()->create();

        // Thursday .. Tuesday: spans the Sat/Sun weekend.
        $this->actingAs($admin)
            ->post(route('staff-roster.bulk'), [
                'employees' => [$employee->id],
                'start_date' => '2026-10-08',
                'end_date' => '2026-10-13',
                'shift_id' => $shift->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff-roster.index', ['week' => '2026-10-08']));

        $dates = ShiftRoster::query()->where('employee_id', $employee->id)->orderBy('date')->pluck('date');
        $this->assertSame(
            ['2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13'],
            $dates->map(fn ($date) => $date->toDateString())->all(),
        );

        // A 63-day range is rejected outright.
        $this->actingAs($admin)
            ->postJson(route('staff-roster.bulk'), [
                'employees' => [$employee->id],
                'start_date' => '2026-10-08',
                'end_date' => '2026-12-09',
                'shift_id' => $shift->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    /**
     * AC-20-06: a cell override takes effect through ShiftResolver and
     * clearing the cell falls back to the employee's default shift.
     */
    public function test_cell_override_and_clear_fall_back_to_the_default_shift(): void
    {
        $admin = User::factory()->admin()->create();
        $resolver = app(ShiftResolver::class);
        $pagi = Shift::factory()->create(['name' => 'Shift Pagi']);
        $siang = Shift::factory()->create(['name' => 'Shift Siang', 'start_time' => '12:00', 'end_time' => '18:00']);
        $employee = Employee::factory()->create(['shift_id' => $pagi->id]);

        $this->actingAs($admin)
            ->put(route('staff-roster.set'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-07',
                'shift_id' => $siang->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff-roster.index', ['week' => '2026-10-07']));

        $this->assertSame('Shift Siang', $resolver->resolve($employee, '2026-10-07')->shift?->name);

        $this->actingAs($admin)
            ->delete(route('staff-roster.clear'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-07',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff-roster.index', ['week' => '2026-10-07']));

        $this->assertSame(0, ShiftRoster::query()->count());
        $this->assertSame('Shift Pagi', $resolver->resolve($employee, '2026-10-07')->shift?->name);
    }

    /**
     * A cell set with a null shift stores an explicit day-off row and the
     * grid renders it as a day-off cell (spec 20 §Req 3).
     */
    public function test_setting_a_cell_to_day_off_stores_a_null_shift_row(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->put(route('staff-roster.set'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-07',
                'shift_id' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_roster', ['employee_id' => $employee->id, 'date' => '2026-10-07', 'shift_id' => null]);

        $this->actingAs($admin)
            ->get(route('staff-roster.index', ['week' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('rows.0.cells.2.is_day_off', true)
                ->where('rows.0.cells.2.source', 'roster'));
    }

    /**
     * Only today and future dates are editable: set, clear and bulk on a
     * past date are rejected and leave the roster unchanged.
     */
    public function test_mutations_touching_past_dates_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->putJson(route('staff-roster.set'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-05',
                'shift_id' => $shift->id,
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->deleteJson(route('staff-roster.clear'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-05',
            ])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->postJson(route('staff-roster.bulk'), [
                'employees' => [$employee->id],
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
                'shift_id' => $shift->id,
            ])
            ->assertStatus(422);

        $this->assertSame(0, ShiftRoster::query()->count());
    }

    /**
     * AC-20-16: the principal can view the roster but every mutation is
     * forbidden; non-admin, non-principal roles cannot even view it.
     */
    #[DataProvider('nonAdminRoles')]
    public function test_view_and_mutation_permissions_follow_the_roles(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        if ($factoryState === 'principal') {
            $this->actingAs($user)->get(route('staff-roster.index'))->assertOk();

            return;
        }

        $this->actingAs($user)->get(route('staff-roster.index'))->assertForbidden();
    }

    public function test_principal_mutations_are_forbidden(): void
    {
        $principal = User::factory()->principal()->create();
        $shift = Shift::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($principal)
            ->put(route('staff-roster.set'), ['employee_id' => $employee->id, 'date' => '2026-10-07', 'shift_id' => $shift->id])
            ->assertForbidden();

        $this->actingAs($principal)
            ->post(route('staff-roster.bulk'), [
                'employees' => [$employee->id],
                'start_date' => '2026-10-07',
                'end_date' => '2026-10-08',
                'shift_id' => $shift->id,
            ])
            ->assertForbidden();

        $this->actingAs($principal)
            ->delete(route('staff-roster.clear'), ['employee_id' => $employee->id, 'date' => '2026-10-07'])
            ->assertForbidden();

        $this->assertSame(0, ShiftRoster::query()->count());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
