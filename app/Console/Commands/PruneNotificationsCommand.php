<?php

namespace App\Console\Commands;

use App\Models\DatabaseNotification;
use App\Models\NotificationDelivery;
use Illuminate\Console\Command;

class PruneNotificationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune read in-app notifications older than 180 days and delivery ledger records older than 365 days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $readCutoff = now()->subDays(180);
        $prunedInApp = DatabaseNotification::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $readCutoff)
            ->delete();

        $deliveryCutoff = now()->subDays(365);
        $prunedDeliveries = NotificationDelivery::query()
            ->where('created_at', '<', $deliveryCutoff)
            ->delete();

        $this->info("Pruned {$prunedInApp} read in-app notifications and {$prunedDeliveries} delivery records.");

        return self::SUCCESS;
    }
}
