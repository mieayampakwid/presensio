<?php

namespace Tests\Unit\Services;

use App\Models\NonSchoolDay;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchoolSettingsTest extends TestCase
{
    use RefreshDatabase;

    private SchoolSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(SchoolSettings::class);
    }

    public function test_spec_defaults_apply(): void
    {
        $this->assertSame('Asia/Jakarta', $this->settings->timezone());
        $this->assertFalse($this->settings->requireCheckout());
        $this->assertSame(1, $this->settings->debounceMinutes());
        $this->assertSame(2, $this->settings->driftToleranceMinutes());
        $this->assertSame('15:30', $this->settings->autoAbsentCronTime());
    }

    public function test_now_resolves_in_the_school_timezone(): void
    {
        Date::setTestNow('2026-09-21 10:00:00', 'UTC');

        $this->assertSame('Asia/Jakarta', $this->settings->now()->tzName);
        $this->assertSame('17:00', $this->settings->now()->format('H:i'));
        $this->assertSame('2026-09-21', $this->settings->todayDate());

        Date::setTestNow();
    }

    public function test_today_date_flips_at_school_midnight_not_utc(): void
    {
        // 17:00 UTC is already next-day 00:00 in Jakarta.
        Date::setTestNow('2026-09-21 17:00:00', 'UTC');

        $this->assertSame('2026-09-21', now()->toDateString(), 'UTC date sanity');
        $this->assertSame('2026-09-22', $this->settings->todayDate());

        Date::setTestNow();
    }

    public function test_is_late_boundaries(): void
    {
        Date::setTestNow('2026-09-21 00:00:00', 'Asia/Jakarta');

        $this->assertFalse($this->settings->isLate(Date::parse('2026-09-21 07:29:00', 'Asia/Jakarta')));
        $this->assertFalse($this->settings->isLate(Date::parse('2026-09-21 07:30:00', 'Asia/Jakarta')), 'on the dot is on time');
        $this->assertTrue($this->settings->isLate(Date::parse('2026-09-21 07:31:00', 'Asia/Jakarta')));
    }

    public function test_is_late_evaluates_an_instant_from_any_timezone(): void
    {
        Date::setTestNow('2026-09-21 00:00:00', 'Asia/Jakarta');

        // 00:31 UTC = 07:31 Jakarta on the same day → late.
        $this->assertTrue($this->settings->isLate(Date::parse('2026-09-21 00:31:00', 'UTC')));
    }

    public function test_is_school_day_rejects_weekends_and_recorded_days(): void
    {
        // 2026-09-19 is a Saturday, 2026-09-21 a Monday.
        $this->assertFalse($this->settings->isSchoolDay('2026-09-19'));
        $this->assertTrue($this->settings->isSchoolDay('2026-09-21'));

        NonSchoolDay::factory()->create(['date' => '2026-09-21']);

        $this->assertFalse($this->settings->isSchoolDay('2026-09-21'));
    }

    public function test_is_school_day_accepts_instant_and_survives_timezone_conversion(): void
    {
        // Jakarta 2026-09-22 00:30 is still the 22nd as a school date.
        $instant = Date::parse('2026-09-21 17:30:00', 'UTC');

        $this->assertTrue($this->settings->isSchoolDay($instant));
    }

    public function test_returns_defaults_before_the_table_exists(): void
    {
        Schema::drop('settings');

        $fresh = new SchoolSettings;

        $this->assertSame('Asia/Jakarta', $fresh->timezone());
        $this->assertFalse($fresh->requireCheckout());
        $this->assertSame('15:30', $fresh->autoAbsentCronTime());
    }

    public function test_start_time_reflects_an_updated_setting(): void
    {
        Date::setTestNow('2026-09-21 00:00:00', 'Asia/Jakarta');

        $row = $this->settings->row();
        $row->update(['school_start_time' => '08:00']);
        app()->forgetInstance(SchoolSettings::class);
        $settings = app(SchoolSettings::class);

        $this->assertSame('08:00', $settings->startTimeOn($settings->now())->format('H:i'));
        $this->assertFalse($settings->isLate(Date::parse('2026-09-21 07:45:00', 'Asia/Jakarta')));

        Date::setTestNow();
    }
}
