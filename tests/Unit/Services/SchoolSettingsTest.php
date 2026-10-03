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
        $this->assertSame([1, 2, 3, 4, 5], $this->settings->operationalWeekdays());
    }

    public function test_now_resolves_in_the_school_timezone(): void
    {
        Date::setTestNow(Date::parse('2026-09-19 12:00:00', 'UTC'));

        // UTC 12:00 is WIB 19:00 (UTC+7)
        $now = $this->settings->now();

        $this->assertSame('Asia/Jakarta', $now->timezoneName);
        $this->assertSame(19, $now->hour);
    }

    public function test_today_date_flips_at_school_midnight_not_utc(): void
    {
        // 23:30 UTC on Sep 19 is 06:30 WIB on Sep 20.
        Date::setTestNow(Date::parse('2026-09-19 23:30:00', 'UTC'));

        $this->assertSame('2026-09-20', $this->settings->todayDate());

        // 16:30 UTC on Sep 19 is 23:30 WIB on Sep 19.
        Date::setTestNow(Date::parse('2026-09-19 16:30:00', 'UTC'));

        $this->assertSame('2026-09-19', $this->settings->todayDate());
    }

    public function test_is_late_boundaries(): void
    {
        // School starts 07:30 WIB.
        $day = Date::parse('2026-09-19 00:00:00', 'Asia/Jakarta');

        $this->assertFalse($this->settings->isLate($day->copy()->setTime(7, 30, 0)));
        $this->assertFalse($this->settings->isLate($day->copy()->setTime(7, 29, 59)));
        $this->assertTrue($this->settings->isLate($day->copy()->setTime(7, 30, 1)));
    }

    public function test_is_late_evaluates_an_instant_from_any_timezone(): void
    {
        // 00:30:01 UTC = 07:30:01 WIB -> late.
        $utc = Date::parse('2026-09-19 00:30:01', 'UTC');

        $this->assertTrue($this->settings->isLate($utc));
    }

    public function test_is_school_day_rejects_weekends_and_recorded_days_by_default(): void
    {
        // 2026-09-20 is Sunday, 2026-09-21 is Monday.
        $this->assertFalse($this->settings->isSchoolDay('2026-09-20'));
        $this->assertTrue($this->settings->isSchoolDay('2026-09-21'));
        NonSchoolDay::create([
            'date' => '2026-09-21',
            'name' => 'Holiday',
            'source' => 'manual',
        ]);
        $this->assertFalse($this->settings->isSchoolDay('2026-09-21'));
    }

    /**
     * AUDIT D-05: 6-day school (Saturday is operational day).
     */
    public function test_six_day_school_considers_saturday_a_school_day(): void
    {
        // Saturday 2026-09-19
        $saturday = '2026-09-19';

        // Default 5-day school: Saturday is not a school day
        $this->assertFalse($this->settings->isSchoolDay($saturday));

        // Configure 6-day school (Monday through Saturday: 1..6)
        $this->settings->row()->update(['school_operational_days' => [1, 2, 3, 4, 5, 6]]);
        $this->settings->refresh();

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->settings->operationalWeekdays());
        $this->assertTrue($this->settings->isSchoolDay($saturday));

        // Sunday is still not a school day
        $this->assertFalse($this->settings->isSchoolDay('2026-09-20'));
    }

    public function test_is_school_day_accepts_instant_and_survives_timezone_conversion(): void
    {
        // 2026-09-20 20:00:00 UTC is Monday 2026-09-21 03:00:00 in WIB -> school day.
        $instant = Date::parse('2026-09-20 20:00:00', 'UTC');

        $this->assertTrue($this->settings->isSchoolDay($instant));
    }

    public function test_returns_defaults_before_the_table_exists(): void
    {
        Schema::dropIfExists('settings');

        $fresh = new SchoolSettings;

        $this->assertSame('Asia/Jakarta', $fresh->timezone());
        $this->assertSame('07:30', $fresh->startTimeOn(Date::now())->format('H:i'));
        $this->assertSame([1, 2, 3, 4, 5], $fresh->operationalWeekdays());
    }

    public function test_start_time_reflects_an_updated_setting(): void
    {
        $this->settings->row()->update(['school_start_time' => '08:00:00']);

        // Clear memoization so it re-reads from DB.
        $this->settings->refresh();

        $day = Date::parse('2026-09-19 00:00:00', 'Asia/Jakarta');

        $this->assertSame('08:00:00', $this->settings->startTimeOn($day)->format('H:i:s'));
        $this->assertFalse($this->settings->isLate($day->copy()->setTime(7, 59, 59)));
        $this->assertTrue($this->settings->isLate($day->copy()->setTime(8, 0, 1)));
    }

    public function test_notification_settings_defaults(): void
    {
        $this->assertSame(500, $this->settings->whatsappDailyQuota());
        $this->assertSame(['start' => '21:00:00', 'end' => '06:00:00'], $this->settings->quietHours());
        $this->assertSame(3, $this->settings->billReminderDaysBefore());

        $channels = $this->settings->notificationChannels();
        $this->assertCount(13, $channels);
        $this->assertTrue($channels['report_card_published']['whatsapp']);
        $this->assertFalse($channels['report_card_published']['email']);
        $this->assertTrue($channels['payment_rejected']['whatsapp']);
        $this->assertFalse($channels['excuse_reviewed']['whatsapp']);
        $this->assertFalse($channels['absence_alert']['whatsapp']);
    }

    public function test_notification_channels_clamps_to_catalog(): void
    {
        // Try to enable WhatsApp and email for absence_alert and excuse_submitted (which are not allowed in catalog)
        $this->settings->row()->update([
            'notification_channels' => [
                'absence_alert' => ['whatsapp' => true, 'email' => true],
                'excuse_submitted' => ['whatsapp' => true, 'email' => true],
                'excuse_reviewed' => ['whatsapp' => true, 'email' => true],
            ],
        ]);
        $this->settings->refresh();

        $channels = $this->settings->notificationChannels();
        // absence_alert and excuse_submitted must be clamped to false
        $this->assertFalse($channels['absence_alert']['whatsapp']);
        $this->assertFalse($channels['absence_alert']['email']);
        $this->assertFalse($channels['excuse_submitted']['whatsapp']);
        $this->assertFalse($channels['excuse_submitted']['email']);
        // excuse_reviewed allows whatsapp and email
        $this->assertTrue($channels['excuse_reviewed']['whatsapp']);
        $this->assertTrue($channels['excuse_reviewed']['email']);
    }
}
