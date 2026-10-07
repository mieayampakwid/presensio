<?php

namespace Tests\Unit\Services\Finance;

use App\Models\AccountingPeriod;
use App\Services\Finance\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_creates_open_period_lazily(): void
    {
        $service = app(PeriodService::class);

        $period = $service->ensure('2026-09');

        $this->assertSame('2026-09', $period->period);
        $this->assertTrue($period->isOpen());
        $this->assertDatabaseHas('accounting_periods', [
            'period' => '2026-09',
            'status' => 'open',
        ]);
    }

    public function test_is_open_returns_true_for_open_period_and_false_for_closed(): void
    {
        $service = app(PeriodService::class);

        $this->assertTrue($service->isOpen('2026-09-15'));

        AccountingPeriod::where('period', '2026-09')->update(['status' => 'closed']);

        $this->assertFalse($service->isOpen('2026-09-15'));
        $this->assertTrue($service->isOpen('2026-10-01'));
    }

    public function test_next_open_period_start_returns_same_date_if_already_open(): void
    {
        $service = app(PeriodService::class);

        $date = Carbon::parse('2026-09-15');
        $resolved = $service->nextOpenPeriodStart($date);

        $this->assertSame('2026-09-15', $resolved->toDateString());
    }

    public function test_next_open_period_start_advances_when_periods_are_closed(): void
    {
        $service = app(PeriodService::class);

        // Close 2026-09 and 2026-10
        AccountingPeriod::create(['period' => '2026-09', 'status' => 'closed']);
        AccountingPeriod::create(['period' => '2026-10', 'status' => 'closed']);

        $resolved = $service->nextOpenPeriodStart('2026-09-20');

        $this->assertSame('2026-11-01', $resolved->toDateString());
        $this->assertTrue($service->isOpen($resolved));
    }
}
