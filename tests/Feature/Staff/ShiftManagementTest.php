<?php

namespace Tests\Feature\Staff;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SchoolSetting;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * AC-20-01: crosses_midnight is derived server-side, never trusted
     * from the client.
     */
    public function test_creating_a_cross_midnight_shift_derives_crosses_midnight(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('shifts.store'), self::shiftPayload([
                'name' => 'Shift Malam',
                'start_time' => '19:00',
                'end_time' => '07:00',
                'crosses_midnight' => '0',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('attendance-settings.edit'));

        $this->assertDatabaseHas('shifts', [
            'name' => 'Shift Malam',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'crosses_midnight' => true,
            'sweep_offset_minutes' => 120,
        ]);
    }

    public function test_admin_can_update_a_shift_keeping_its_own_name(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create(['name' => 'Shift Siang', 'start_time' => '08:00', 'end_time' => '16:00']);

        $this->actingAs($admin)
            ->put(route('shifts.update', $shift), self::shiftPayload([
                'name' => 'Shift Siang',
                'start_time' => '19:00',
                'end_time' => '07:00',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('attendance-settings.edit'));

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'name' => 'Shift Siang',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'crosses_midnight' => true,
        ]);
    }

    public function test_duplicate_shift_names_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Shift::factory()->create(['name' => 'Shift Pagi']);

        $this->actingAs($admin)
            ->postJson(route('shifts.store'), self::shiftPayload(['name' => 'Shift Pagi']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Shift::count());
    }

    public function test_start_time_equal_to_end_time_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('shifts.store'), self::shiftPayload(['start_time' => '08:00', 'end_time' => '08:00']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_time');

        $this->assertSame(0, Shift::count());
    }

    public function test_sweep_offset_outside_the_30_480_range_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([29, 481] as $offset) {
            $this->actingAs($admin)
                ->postJson(route('shifts.store'), self::shiftPayload(['sweep_offset_minutes' => $offset]))
                ->assertStatus(422)
                ->assertJsonValidationErrors('sweep_offset_minutes');
        }

        $this->assertSame(0, Shift::count());
    }

    /**
     * Review Focus 6: start + offset must stay on the shift's start date,
     * or the scheduled sweep would fire the next day against a shift that
     * has not started yet.
     */
    public function test_sweep_offset_pushing_past_midnight_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        // 23:00 + 480min = 07:00 the next calendar day.
        $this->actingAs($admin)
            ->postJson(route('shifts.store'), self::shiftPayload([
                'name' => 'Shift Dinas Malam',
                'start_time' => '23:00',
                'end_time' => '07:00',
                'sweep_offset_minutes' => 480,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sweep_offset_minutes');

        // Exactly 24:00 is also beyond the start date.
        $this->actingAs($admin)
            ->postJson(route('shifts.store'), self::shiftPayload([
                'name' => 'Shift Sore',
                'start_time' => '16:00',
                'end_time' => '23:00',
                'sweep_offset_minutes' => 480,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('sweep_offset_minutes');

        $this->assertSame(0, Shift::count());
    }

    public function test_an_eleventh_shift_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        Shift::factory()->count(10)->create();

        $this->actingAs($admin)
            ->postJson(route('shifts.store'), self::shiftPayload(['name' => 'Shift Sebelas']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift');

        $this->assertSame(10, Shift::count());
    }

    /**
     * AC-20-08: an employee default blocks deletion.
     */
    public function test_deleting_a_shift_used_as_an_employee_default_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create();
        Employee::factory()->create(['shift_id' => $shift->id]);

        $this->actingAs($admin)
            ->deleteJson(route('shifts.destroy', $shift))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    /**
     * AC-20-08: a roster row blocks deletion.
     */
    public function test_deleting_a_shift_referenced_by_a_roster_row_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create();
        ShiftRoster::factory()->create(['shift_id' => $shift->id]);

        $this->actingAs($admin)
            ->deleteJson(route('shifts.destroy', $shift))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    /**
     * AC-20-08: an attendance snapshot blocks deletion.
     */
    public function test_deleting_a_shift_snapshotted_by_an_attendance_record_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create();
        EmployeeAttendance::factory()->create(['shift_id' => $shift->id]);

        $this->actingAs($admin)
            ->deleteJson(route('shifts.destroy', $shift))
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_admin_can_delete_an_unreferenced_shift(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create();

        $this->actingAs($admin)
            ->delete(route('shifts.destroy', $shift))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('attendance-settings.edit'));

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_settings_page_passes_shifts_and_staff_fallback_hours(): void
    {
        $admin = User::factory()->admin()->create();
        Shift::factory()->night()->create();

        $this->actingAs($admin)
            ->get(route('attendance-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/attendance')
                ->where('settings.staff_start_time', '07:00')
                ->where('settings.staff_end_time', '14:00')
                ->where('settings.staff_absent_sweep_time', '09:00')
                ->has('shifts', 1)
                ->where('shifts.0.name', 'Shift Malam')
                ->where('shifts.0.start_time', '19:00')
                ->where('shifts.0.end_time', '07:00')
                ->where('shifts.0.crosses_midnight', true)
                ->where('shifts.0.sweep_offset_minutes', 120));
    }

    public function test_staff_fallback_hours_update_persists_and_audits(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('attendance-settings.update'), [
                'school_timezone' => 'Asia/Jakarta',
                'school_start_time' => '07:30',
                'auto_absent_cron_time' => '15:30',
                'require_checkout' => '1',
                'scan_debounce_minutes' => '1',
                'scan_drift_tolerance_minutes' => '2',
                'school_operational_days' => [1, 2, 3, 4, 5],
                'staff_start_time' => '08:00',
                'staff_end_time' => '15:00',
                'staff_absent_sweep_time' => '10:00',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('attendance-settings.edit'));

        $row = SchoolSetting::query()->sole();
        $this->assertSame('08:00', substr((string) $row->staff_start_time, 0, 5));
        $this->assertSame('15:00', substr((string) $row->staff_end_time, 0, 5));
        $this->assertSame('10:00', substr((string) $row->staff_absent_sweep_time, 0, 5));

        $log = AuditLog::query()
            ->where('auditable_type', (new SchoolSetting)->getMorphClass())
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('08:00', $log->new_values['staff_start_time']);
        $this->assertSame('15:00', $log->new_values['staff_end_time']);
        $this->assertSame('10:00', $log->new_values['staff_absent_sweep_time']);
    }

    /**
     * AC-20-16 (shift CRUD part): no role other than admin may mutate
     * shifts or the staff fallback hours.
     */
    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_shift_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();
        $shift = Shift::factory()->create();

        $this->actingAs($user)
            ->post(route('shifts.store'), self::shiftPayload())
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('shifts.update', $shift), self::shiftPayload())
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('shifts.destroy', $shift))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('attendance-settings.update'), [])
            ->assertForbidden();

        $this->assertSame(1, Shift::count());
    }

    public function test_employee_form_accepts_a_default_shift_and_the_index_shows_it(): void
    {
        $admin = User::factory()->admin()->create();
        $shift = Shift::factory()->create(['name' => 'Shift Malam']);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => 'Budi Santoso',
                'employment_type' => 'permanent',
                'is_active' => '1',
                'is_teacher' => '0',
                'shift_id' => $shift->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseHas('employees', ['name' => 'Budi Santoso', 'shift_id' => $shift->id]);

        $this->actingAs($admin)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('employees.data.0.shift_name', 'Shift Malam'));
    }

    public function test_employee_form_rejects_an_unknown_shift(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('employees.store'), [
                'name' => 'Budi Santoso',
                'employment_type' => 'permanent',
                'is_active' => '1',
                'is_teacher' => '0',
                'shift_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shift_id');

        $this->assertDatabaseMissing('employees', ['name' => 'Budi Santoso']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function shiftPayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Shift Pagi',
            'start_time' => '07:00',
            'end_time' => '14:00',
            'sweep_offset_minutes' => 120,
        ], $overrides);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonAdminRoles(): array
    {
        return [
            'principal' => ['principal'],
            'teacher' => ['teacher'],
            'counselor' => ['counselor'],
            'finance' => ['finance'],
            'staff' => ['staff'],
            'parent' => ['parent'],
            'student' => ['student'],
        ];
    }
}
