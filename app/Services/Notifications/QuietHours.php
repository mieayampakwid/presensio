<?php

namespace App\Services\Notifications;

use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class QuietHours
{
    public function __construct(private readonly SchoolSettings $schoolSettings) {}

    /**
     * Determine whether the given instant falls in the quiet hours window.
     */
    public function isQuietTime(?CarbonInterface $instant = null): bool
    {
        $instant ??= $this->schoolSettings->now();
        $tz = $this->schoolSettings->timezone();
        $current = Carbon::instance($instant)->setTimezone($tz);

        $quietHours = $this->schoolSettings->quietHours();
        $start = $quietHours['start'];
        $end = $quietHours['end'];

        $currentTime = $current->format('H:i:s');

        // Normal case: e.g. 21:00:00 to 06:00:00 (spans across midnight)
        if ($start > $end) {
            return $currentTime >= $start || $currentTime < $end;
        }

        // Intra-day case: e.g. 12:00:00 to 14:00:00
        return $currentTime >= $start && $currentTime < $end;
    }

    /**
     * Compute the end of the current quiet hours window when delayed notifications can be sent.
     */
    public function delayUntil(?CarbonInterface $instant = null): CarbonInterface
    {
        $instant ??= $this->schoolSettings->now();
        $tz = $this->schoolSettings->timezone();
        $current = Carbon::instance($instant)->setTimezone($tz);

        $quietHours = $this->schoolSettings->quietHours();
        $start = $quietHours['start'];
        $end = $quietHours['end'];

        $currentTime = $current->format('H:i:s');

        if ($start > $end) {
            if ($currentTime >= $start) {
                // E.g. 22:00 at night -> ends tomorrow at 06:00
                return $current->copy()->addDay()->setTimeFromTimeString($end)->setTimezone('UTC');
            }

            // E.g. 04:00 early morning -> ends today at 06:00
            return $current->copy()->setTimeFromTimeString($end)->setTimezone('UTC');
        }

        return $current->copy()->setTimeFromTimeString($end)->setTimezone('UTC');
    }
}
