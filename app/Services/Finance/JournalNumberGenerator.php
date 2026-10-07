<?php

namespace App\Services\Finance;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class JournalNumberGenerator
{
    /**
     * Generate the next sequential journal entry number for the date's month (JU-YYYYMM-NNNNN).
     * Must be called within a transaction so the sequence row is locked FOR UPDATE.
     */
    public function generate(CarbonInterface|string $date): string
    {
        $carbon = is_string($date) ? Carbon::parse($date) : $date;
        $period = $carbon->format('Ym');

        DB::table('journal_sequences')->insertOrIgnore([
            'period' => $period,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var object{period: string, last_number: int|string} $seq */
        $seq = DB::table('journal_sequences')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        $nextNumber = ((int) $seq->last_number) + 1;

        DB::table('journal_sequences')
            ->where('period', $period)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        return sprintf('JU-%s-%05d', $period, $nextNumber);
    }
}
