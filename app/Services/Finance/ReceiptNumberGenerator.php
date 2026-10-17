<?php

namespace App\Services\Finance;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ReceiptNumberGenerator
{
    /**
     * Generate the next sequential receipt number for the date's month (KWT-YYYYMM-NNNNN).
     * Must be called within a transaction so the sequence row is locked FOR UPDATE.
     */
    public function generate(CarbonInterface $date): string
    {
        $period = $date->format('Ym');

        DB::table('receipt_sequences')->insertOrIgnore(['period' => $period, 'last_number' => 0]);

        /** @var object{period: string, last_number: int|string} $seq */
        $seq = DB::table('receipt_sequences')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $seq->last_number) + 1;

        DB::table('receipt_sequences')->where('period', $period)->update(['last_number' => $nextNumber]);

        return sprintf('KWT-%s-%05d', $period, $nextNumber);
    }
}
