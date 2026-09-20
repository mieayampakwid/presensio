<?php

namespace App\Observers;

use App\Jobs\SendAbsenceNotifications;
use App\Models\Attendance;

/**
 * Spec 05 trigger: a CREATED attendance record whose status is enabled
 * in config notifies the linked guardians asynchronously (default:
 * absent only — see config/attendance.php notifications for the cost
 * caveats of enabling more). Edits never notify and never retract
 * (spec 05 §Decisions) — only creation dispatches. The scan service
 * (present/late) and excuse approval (sick/leave) pass through this
 * hook without matching the guard under the default config.
 */
class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        if (in_array($attendance->status->value, $this->statuses(), true)) {
            SendAbsenceNotifications::dispatch($attendance);
        }
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        $statuses = config('attendance.notifications.statuses');

        return is_array($statuses) ? array_values($statuses) : [];
    }
}
