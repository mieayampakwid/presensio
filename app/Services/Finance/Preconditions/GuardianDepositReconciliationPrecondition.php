<?php

namespace App\Services\Finance\Preconditions;

use App\Models\AccountingPeriod;
use App\Services\Finance\Contracts\ClosePrecondition;
use App\Services\Finance\GuardianDepositReconciliation;
use App\Support\Money;
use Illuminate\Support\Carbon;
use RuntimeException;

class GuardianDepositReconciliationPrecondition implements ClosePrecondition
{
    public function __construct(private readonly GuardianDepositReconciliation $reconciliation) {}

    public function check(AccountingPeriod $period): void
    {
        $result = $this->reconciliation->asOf(Carbon::parse($period->period.'-01')->endOfMonth());
        if (! $result['is_balanced']) {
            throw new RuntimeException('Rekonsiliasi Titipan Wali tidak seimbang: selisih '.Money::format($result['total_difference']).'. Periksa bagian Titipan Wali di Laporan Rekonsiliasi.');
        }
    }
}
