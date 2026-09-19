<?php

namespace Tests\Feature\Settings;

use App\Models\SchoolSetting;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_the_seeded_defaults(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('attendance-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/attendance')
                ->where('settings.school_timezone', 'Asia/Jakarta')
                ->where('settings.school_start_time', '07:30')
                ->where('settings.auto_absent_cron_time', '15:30')
                ->where('settings.require_checkout', false)
                ->where('settings.scan_debounce_minutes', 1)
                ->where('settings.scan_drift_tolerance_minutes', 2));
    }

    public function test_admin_updates_every_field(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('attendance-settings.update'), [
                'school_timezone' => 'Asia/Makassar',
                'school_start_time' => '08:00',
                'auto_absent_cron_time' => '16:00',
                'require_checkout' => '1',
                'scan_debounce_minutes' => '5',
                'scan_drift_tolerance_minutes' => '3',
            ])
            ->assertRedirect(route('attendance-settings.edit'));

        $row = SchoolSetting::query()->sole();
        $this->assertSame('Asia/Makassar', $row->school_timezone);
        $this->assertSame('08:00', substr((string) $row->school_start_time, 0, 5));
        $this->assertSame('16:00', substr((string) $row->auto_absent_cron_time, 0, 5));
        $this->assertTrue($row->require_checkout);
        $this->assertSame(5, $row->scan_debounce_minutes);
        $this->assertSame(3, $row->scan_drift_tolerance_minutes);

        // The singleton memoizes per process; a fresh process would re-read.
        app()->forgetInstance(SchoolSettings::class);

        $this->actingAs($admin)
            ->get(route('attendance-settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('settings.school_timezone', 'Asia/Makassar')
                ->where('settings.require_checkout', true));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('attendance-settings.update'), [
                'school_timezone' => 'Mars/Olympus',
                'school_start_time' => '25:00',
                'auto_absent_cron_time' => '15:30',
                'require_checkout' => '1',
                'scan_debounce_minutes' => '999',
                'scan_drift_tolerance_minutes' => '-1',
            ])
            ->assertSessionHasErrors([
                'school_timezone',
                'school_start_time',
                'scan_debounce_minutes',
                'scan_drift_tolerance_minutes',
            ]);

        $this->assertSame('Asia/Jakarta', SchoolSetting::query()->sole()->school_timezone);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('attendance-settings.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('attendance-settings.update'), [
                'school_timezone' => 'UTC',
                'school_start_time' => '07:30',
                'auto_absent_cron_time' => '15:30',
                'require_checkout' => '0',
                'scan_debounce_minutes' => '1',
                'scan_drift_tolerance_minutes' => '2',
            ])
            ->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [
            ['teacher'],
            ['student'],
            ['parent'],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('attendance-settings.edit'))->assertRedirect(route('login'));
    }
}
