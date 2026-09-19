<?php

namespace Tests\Unit\Models;

use App\Models\SchoolSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_exactly_one_row_with_spec_defaults(): void
    {
        $setting = SchoolSetting::query()->sole();

        $this->assertSame(1, SchoolSetting::count());
        $this->assertSame('Asia/Jakarta', $setting->school_timezone);
        $this->assertSame('07:30', substr($setting->school_start_time, 0, 5));
        $this->assertFalse($setting->require_checkout);
        $this->assertSame('15:30', substr($setting->auto_absent_cron_time, 0, 5));
        $this->assertSame(1, $setting->scan_debounce_minutes);
        $this->assertSame(2, $setting->scan_drift_tolerance_minutes);
    }

    public function test_require_checkout_casts_to_a_boolean(): void
    {
        $setting = SchoolSetting::query()->sole();

        $setting->update(['require_checkout' => true]);

        $this->assertTrue($setting->refresh()->require_checkout);
        $this->assertIsBool($setting->require_checkout);
    }

    public function test_minute_settings_cast_to_integers(): void
    {
        $setting = SchoolSetting::query()->sole();

        $setting->update(['scan_debounce_minutes' => 5, 'scan_drift_tolerance_minutes' => 3]);

        $this->assertSame(5, $setting->refresh()->scan_debounce_minutes);
        $this->assertSame(3, $setting->scan_drift_tolerance_minutes);
    }
}
