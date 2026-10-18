<?php

namespace App\Services\Finance\Preconditions;

use App\Models\AccountingPeriod;
use App\Services\Finance\Contracts\ClosePrecondition;
use App\Services\Finance\Reports\TrialBalanceReport;
use Illuminate\Support\Carbon;
use RuntimeException;

class TrialBalanceClosePrecondition implements ClosePrecondition
{
    public function __construct(
        private readonly TrialBalanceReport $trialBalanceReport,
    ) {}

    /**
     * Check if trial balance balances at the period's month-end.
     *
     * @throws RuntimeException
     */
    public function check(AccountingPeriod $period): void
    {
        $endOfMonth = Carbon::parse($period->period.'-01')
            ->endOfMonth()
            ->toDateString();

        $tb = $this->trialBalanceReport->generate($endOfMonth);

        if (! $tb['is_balanced']) {
            throw new RuntimeException("Neraca saldo belum seimbang (selisih: Rp {$tb['difference']}). Periode tidak dapat ditutup.");
        }
    }
}
