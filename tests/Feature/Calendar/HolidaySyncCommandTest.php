<?php

namespace Tests\Feature\Calendar;

use App\Models\NonSchoolDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidaySyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-09-20 05:00:00', 'UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    public function test_sync_imports_holidays_and_reports_the_result(): void
    {
        Http::fake([
            '*/PublicHolidays/2026/ID' => Http::response([
                ['date' => '2026-12-25', 'localName' => 'Natal', 'name' => 'Christmas Day', 'global' => true],
            ]),
            '*/PublicHolidays/2027/ID' => Http::response([]),
        ]);

        $this->artisan('attendance:sync-holidays')
            ->assertSuccessful()
            ->expectsOutputToContain('imported 1');

        $this->assertSame(1, NonSchoolDay::count());
    }

    public function test_feed_outages_fail_soft_and_still_succeed(): void
    {
        Http::fake(['*/PublicHolidays/*' => Http::response([], 500)]);

        $this->artisan('attendance:sync-holidays')
            ->assertSuccessful()
            ->expectsOutputToContain('failed');

        $this->assertSame(0, NonSchoolDay::count());
    }
}
