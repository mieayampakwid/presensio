<?php

namespace App\Services\Finance\Preconditions;

use App\Models\AccountingPeriod;
use App\Services\Finance\Contracts\ClosePrecondition;
use App\Services\Finance\ReceivablesReconciliation;
use App\Support\Money;
use Illuminate\Support\Carbon;
use RuntimeException;

class ReceivablesReconciliationPrecondition implements ClosePrecondition
{
    public function __construct(
        private readonly ReceivablesReconciliation $reconciliation,
    ) {}

    /**
     * Check that GL and the bill subledger agree at the period's month-end.
     *
     * @throws RuntimeException
     */
    public function check(AccountingPeriod $period): void
    {
        $endOfMonth = Carbon::parse($period->period.'-01')->endOfMonth()->toDateString();

        $result = $this->reconciliation->asOf($endOfMonth);

        if (! $result['is_balanced']) {
            throw new RuntimeException('Rekonsiliasi piutang tidak seimbang: selisih '.Money::format($result['total_difference']).'; periksa Laporan Rekonsiliasi Piutang.');
        }
    }
}
