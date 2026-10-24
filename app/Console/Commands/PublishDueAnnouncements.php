<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Services\Announcements\AnnouncementPublisher;
use Illuminate\Console\Command;
use Throwable;

class PublishDueAnnouncements extends Command
{
    protected $signature = 'announcements:publish-due';

    protected $description = 'Publish due announcements and recover unfinished notification fanout';

    public function handle(AnnouncementPublisher $publisher): int
    {
        $failed = false;
        Announcement::where('published_at', '<=', now())->whereNull('notifications_dispatched_at')->chunkById(100, function ($items) use ($publisher, &$failed): void {
            foreach ($items as $item) {
                try {
                    $publisher->publishDue($item);
                } catch (Throwable $e) {
                    $failed = true;
                    report($e);
                }
            }
        });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
