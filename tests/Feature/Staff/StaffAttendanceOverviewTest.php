<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffAttendanceOverviewTest extends TestCase
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

    public function test_admin_and_principal_can_view_daily_overview(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->create();
        $principal->roleGrants()->create(['role' => UserRole::Principal]);

        // Monday 2026-10-05
        $this->actingAs($admin)
            ->get(route('staff-attendance.index', ['date' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/attendance')
                ->where('date', '2026-10-05')
                ->where('can_override', true));

        $this->actingAs($principal)
            ->get(route('staff-attendance.index', ['date' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/attendance')
                ->where('date', '2026-10-05')
                ->where('can_override', false));
    }

    public function test_unauthorized_roles_receive_403(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->create();

        $staffUser = User::factory()->create();
        $staffUser->roleGrants()->create(['role' => UserRole::Staff]);

        foreach ([$teacher, $student, $parent, $staffUser] as $user) {
            $this->actingAs($user)
                ->get(route('staff-attendance.index'))
                ->assertForbidden();
        }
    }

    public function test_overview_lists_expected_employees_with_statuses_and_filters(): void
    {
        $admin = User::factory()->admin()->create();

        // 2026-10-05 is Monday
        $emp1 = Employee::factory()->create(['name' => 'Budi Santoso', 'working_days' => null]);
        $emp2 = Employee::factory()->create(['name' => 'Siti Aminah', 'working_days' => [1, 3]]);
        $emp3 = Employee::factory()->create(['name' => 'Joko Widodo', 'working_days' => [2, 4]]); // Not expected on Monday

        EmployeeAttendance::factory()->create([
            'employee_id' => $emp1->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present,
            'checked_in_at' => Date::parse('2026-10-05 06:55:00', 'Asia/Jakarta'),
            'checked_out_at' => Date::parse('2026-10-05 14:05:00', 'Asia/Jakarta'),
        ]);

        $this->actingAs($admin)
            ->get(route('staff-attendance.index', ['date' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/attendance')
                ->has('rows', 2) // emp1 and emp2 (emp3 is not expected and has no record)
                ->where('summary.expected', 2)
                ->where('summary.arrived', 1)
                ->where('summary.not_yet_arrived', 1));
    }

    public function test_no_checkout_is_derived_for_past_dates(): void
    {
        $admin = User::factory()->admin()->create();

        // Freeze time to today: 2026-10-06 (Tuesday)
        Date::setTestNow('2026-10-06 08:00:00', 'Asia/Jakarta');

        $emp = Employee::factory()->create(['name' => 'Budi Santoso', 'working_days' => null]);

        // Yesterday (2026-10-05): checked in, but never checked out
        EmployeeAttendance::factory()->create([
            'employee_id' => $emp->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present,
            'checked_in_at' => Date::parse('2026-10-05 06:55:00', 'Asia/Jakarta'),
            'checked_out_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('staff-attendance.index', ['date' => '2026-10-05']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('staff/attendance')
                ->where('summary.no_checkout', 1)
                ->where('rows.0.no_checkout', true));
    }

    public function test_admin_can_upsert_manual_override_record_with_auditing(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin.chief']);
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->put(route('staff-attendance.upsert'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-05',
                'status' => EmployeeAttendanceStatus::Present->value,
                'checked_in_at' => '07:15',
                'checked_out_at' => '14:30',
                'notes' => 'Koreksi manual lupa tap kartu',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff-attendance.index', ['date' => '2026-10-05']));

        $record = EmployeeAttendance::where('employee_id', $employee->id)->where('date', '2026-10-05')->first();
        $this->assertNotNull($record);
        $this->assertSame(EmployeeAttendanceStatus::Present, $record->status);
        $this->assertSame(15, $record->late_minutes); // 07:15 is 15 min after 07:00
        $this->assertSame(0, $record->early_leave_minutes); // 14:30 is after 14:00
        $this->assertSame(ScanMethod::ManualOverride, $record->scan_method);
        $this->assertSame($admin->id, $record->override_by_user_id);
        $this->assertNotNull($record->overridden_at);
        $this->assertSame('Koreksi manual lupa tap kartu', $record->notes);

        // Verify audit log
        $audit = AuditLog::where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('created', $audit->action);
    }

    public function test_manual_override_rejects_malformed_or_reversed_times(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create();
        $payload = [
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present->value,
        ];

        $this->actingAs($admin)
            ->put(route('staff-attendance.upsert'), [...$payload, 'checked_in_at' => 'abc'])
            ->assertSessionHasErrors('checked_in_at');

        $this->actingAs($admin)
            ->put(route('staff-attendance.upsert'), [...$payload, 'checked_in_at' => '14:00', 'checked_out_at' => '07:00'])
            ->assertSessionHasErrors('checked_out_at');

        $this->assertSame(0, EmployeeAttendance::count());
    }

    public function test_malformed_date_filter_falls_back_to_today(): void
    {
        Date::setTestNow('2026-10-06 08:00:00', 'Asia/Jakarta');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('staff-attendance.index', ['date' => 'foo']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-06'));
    }

    public function test_principal_cannot_write_manual_override(): void
    {
        $principal = User::factory()->create();
        $principal->roleGrants()->create(['role' => UserRole::Principal]);
        $employee = Employee::factory()->create();

        $this->actingAs($principal)
            ->put(route('staff-attendance.upsert'), [
                'employee_id' => $employee->id,
                'date' => '2026-10-05',
                'status' => EmployeeAttendanceStatus::Present->value,
            ])
            ->assertForbidden();
    }
}
