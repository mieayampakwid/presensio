<?php

namespace App\Services\Finance;

use App\Models\AccountingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class PeriodService
{
    /**
     * Ensure the accounting period row exists, lazily creating it as open if missing.
     */
    public function ensure(string $period): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['period' => $period],
            ['status' => 'open']
        );
    }

    /**
     * Check if the accounting period covering the given date is open.
     */
    public function isOpen(CarbonInterface|string $date): bool
    {
        $carbon = is_string($date) ? Carbon::parse($date) : $date;
        $periodKey = $carbon->format('Y-m');

        return $this->ensure($periodKey)->isOpen();
    }

    /**
     * Resolve the start date of the next open period if the given date's period is closed.
     * If the period is already open, returns the given date.
     */
    public function nextOpenPeriodStart(CarbonInterface|string $date): CarbonInterface
    {
        $current = is_string($date) ? Carbon::parse($date)->startOfDay() : $date->copy()->startOfDay();

        while (! $this->isOpen($current)) {
            $current = $current->copy()->addMonth()->startOfMonth();
        }

        return $current;
    }
}
