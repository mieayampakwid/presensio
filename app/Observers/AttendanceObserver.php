<?php

namespace App\Observers;

use App\Enums\AttendanceStatus;
use App\Jobs\SendAbsenceNotifications;
use App\Models\Attendance;

/**
 * Spec 05 trigger: a CREATED attendance record with status absent
 * notifies the linked guardians asynchronously. Edits never notify and
 * never retract (spec 05 §Decisions) — only creation dispatches. The
 * scan service (present/late only) and excuse approval (sick/leave
 * only) pass through this hook without ever matching the guard.
 */
class AttendanceObserver
{
    public function created(Attendance $attendance): void
    {
        if ($attendance->status === AttendanceStatus::Absent) {
            SendAbsenceNotifications::dispatch($attendance);
        }
    }
}
