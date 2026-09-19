<?php

namespace App\Console\Commands;

use App\Services\Calendar\HolidaySyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('attendance:sync-holidays')]
#[Description('Sync national holidays from the external calendar feed')]
class SyncHolidaysCommand extends Command
{
    /**
     * Weekly one-way sync (spec 03 §Requirements 7). Fail-soft by design:
     * the service reports failures and the command still succeeds so the
     * schedule stays healthy — next week's run retries.
     */
    public function handle(HolidaySyncService $sync): int
    {
        $result = $sync->sync();

        if (isset($result['failed'])) {
            $this->warn("Holiday sync failed: {$result['failed']}");

            return self::SUCCESS;
        }

        $this->info("Holiday sync imported {$result['imported']}, updated {$result['updated']}.");

        return self::SUCCESS;
    }
}
