<?php

namespace Tests\Unit\Services\Calendar;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use App\Services\Calendar\HolidaySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidaySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sunday 2026-09-20 in Jakarta — sync covers 2026 and 2027.
        Date::setTestNow('2026-09-20 05:00:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<int, mixed>  $for2026
     * @param  array<int, mixed>  $for2027
     */
    private function fakeFeed(array $for2026 = [], array $for2027 = []): void
    {
        Http::fake([
            '*/PublicHolidays/2026/ID' => Http::response($for2026),
            '*/PublicHolidays/2027/ID' => Http::response($for2027),
        ]);
    }

    public function test_imports_future_national_holidays_with_local_names(): void
    {
        $this->fakeFeed(
            [
                ['date' => '2026-12-25', 'localName' => 'Natal', 'name' => 'Christmas Day', 'global' => true],
                ['date' => '2026-08-17', 'localName' => 'Hari Kemerdekaan', 'name' => 'Independence Day', 'global' => true],
                ['date' => '2026-05-01', 'localName' => 'Regional', 'name' => 'Provincial', 'global' => false],
            ],
            [
                ['date' => '2027-01-01', 'localName' => 'Tahun Baru', 'name' => "New Year's Day", 'global' => true],
            ],
        );

        $result = (app(HolidaySyncService::class))->sync();

        $this->assertSame(['imported' => 2, 'updated' => 0], $result);
        $this->assertSame(2, NonSchoolDay::count());

        $natal = NonSchoolDay::query()->where('date', '2026-12-25')->sole();
        $this->assertSame('Natal', $natal->name);
        $this->assertTrue($natal->source === NonSchoolDaySource::Sync);
    }

    public function test_reruns_are_idempotent(): void
    {
        $feed = [['date' => '2026-12-25', 'localName' => 'Natal', 'name' => 'Christmas Day', 'global' => true]];
        $this->fakeFeed($feed, []);

        (app(HolidaySyncService::class))->sync();

        Http::fake([
            '*/PublicHolidays/2026/ID' => Http::response($feed),
            '*/PublicHolidays/2027/ID' => Http::response([]),
        ]);

        $result = (app(HolidaySyncService::class))->sync();

        $this->assertSame(['imported' => 0, 'updated' => 0], $result);
        $this->assertSame(1, NonSchoolDay::count());
    }

    public function test_manual_rows_are_never_touched(): void
    {
        NonSchoolDay::factory()->create(['date' => '2026-12-24', 'name' => 'Teacher training day']);
        $this->fakeFeed(
            [['date' => '2026-12-25', 'localName' => 'Natal', 'name' => 'Christmas Day', 'global' => true]],
            [],
        );

        $result = (app(HolidaySyncService::class))->sync();

        $this->assertSame(['imported' => 1, 'updated' => 0], $result);
        $manual = NonSchoolDay::query()->where('date', '2026-12-24')->sole();
        $this->assertSame('Teacher training day', $manual->name);
        $this->assertTrue($manual->source === NonSchoolDaySource::Manual);
    }

    public function test_synced_row_names_follow_feed_renames(): void
    {
        NonSchoolDay::factory()->synced()->create(['date' => '2026-12-25', 'name' => 'Old name']);
        $this->fakeFeed(
            [['date' => '2026-12-25', 'localName' => 'Natal', 'name' => 'Christmas Day', 'global' => true]],
            [],
        );

        $result = (app(HolidaySyncService::class))->sync();

        $this->assertSame(['imported' => 0, 'updated' => 1], $result);
        $this->assertSame('Natal', NonSchoolDay::query()->where('date', '2026-12-25')->sole()->name);
    }

    public function test_feed_errors_fail_soft_with_nothing_written(): void
    {
        Http::fake(['*/PublicHolidays/*' => Http::response(['oops'], 500)]);

        $result = (app(HolidaySyncService::class))->sync();

        $this->assertArrayHasKey('failed', $result);
        $this->assertSame(0, NonSchoolDay::count());
    }
}
