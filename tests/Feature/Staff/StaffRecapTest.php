<?php

namespace Tests\Feature\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaffRecapTest extends TestCase
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

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_admin_and_principal_can_view_monthly_recap(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();

        foreach ([$admin, $principal] as $user) {
            $this->actingAs($user)
                ->get(route('staff-attendance.recap.index', ['month' => '2026-10']))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('staff/recap')
                    ->where('month', '2026-10')
                    ->has('rows')
                    ->has('summary'));
        }
    }

    public function test_missing_or_malformed_month_falls_back_to_current_month(): void
    {
        Date::setTestNow('2026-10-15 10:00:00');
        $admin = User::factory()->admin()->create();

        foreach ([[], ['month' => 'not-a-month']] as $query) {
            $this->actingAs($admin)
                ->get(route('staff-attendance.recap.index', $query))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('month', '2026-10'));
        }
    }

    public function test_unauthorized_roles_receive_403(): void
    {
        $teacher = User::factory()->teacher()->create();
        $staffUser = User::factory()->staff()->create();
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->create();

        foreach ([$teacher, $staffUser, $student, $parent] as $user) {
            $this->actingAs($user)
                ->get(route('staff-attendance.recap.index', ['month' => '2026-10']))
                ->assertForbidden();

            $this->actingAs($user)
                ->get(route('staff-attendance.recap.export', ['month' => '2026-10']))
                ->assertForbidden();
        }
    }

    public function test_export_streams_csv_with_utf8_bom_and_matching_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = Employee::factory()->create([
            'name' => 'Budi Santoso',
            'employee_number' => '123456789012345678',
        ]);

        EmployeeAttendance::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-10-05',
            'status' => EmployeeAttendanceStatus::Present,
            'checked_in_at' => '2026-10-05 07:00:00',
            'checked_out_at' => '2026-10-05 14:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('staff-attendance.recap.export', ['month' => '2026-10']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString(
            'rekap-kehadiran-staf-2026-10.csv',
            (string) $response->headers->get('Content-Disposition'),
        );

        $content = $response->streamedContent();

        $this->assertTrue(
            str_starts_with($content, "\xEF\xBB\xBF"),
            'CSV export must start with the UTF-8 BOM.',
        );

        $lines = explode("\n", rtrim(preg_replace('/^\xEF\xBB\xBF/', '', $content)));

        $this->assertSame([
            'NIP/NIK',
            'Nama',
            'Hari Kerja (Expected)',
            'Hadir',
            'Terlambat (kali)',
            'Terlambat (menit)',
            'Pulang Cepat (kali)',
            'Pulang Cepat (menit)',
            'Tidak Absen Pulang',
            'Sakit',
            'Izin',
            'Cuti',
            'Dinas Luar',
            'Alpa',
            'Hari Tambahan (Extra)',
            'Persentase Kehadiran (%)',
        ], str_getcsv($lines[0]));

        // 1 of 22 Mon–Fri days attended → (1 / 22) × 100 = 4.5%.
        $this->assertSame([
            '123456789012345678',
            'Budi Santoso',
            '22',
            '1',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '0',
            '4.5',
        ], str_getcsv($lines[1]));
    }
}
