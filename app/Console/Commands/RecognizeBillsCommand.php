<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\Finance\BillService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('finance:recognize-bills')]
#[Description('Post recognition entries for bills whose recognition date has been reached')]
class RecognizeBillsCommand extends Command
{
    /**
     * Daily recognition job (spec 14 §Accrual posting rules). Idempotent: BillService
     * re-checks each bill under lock, so re-runs post nothing new.
     */
    public function handle(BillService $billService): int
    {
        $recognized = 0;

        Bill::query()
            ->whereNull('recognized_at')
            ->whereNull('cancelled_at')
            ->whereNull('written_off_at')
            ->with('feeType')
            ->eachById(function (Bill $bill) use ($billService, &$recognized) {
                if ($billService->recognize($bill)) {
                    $recognized++;
                }
            });

        $this->info("Recognized {$recognized} bills.");

        return self::SUCCESS;
    }
}
