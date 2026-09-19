<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Applies the admin review decision for an excuse (spec 04). Approval is
 * the only path that writes attendance: every school day inside the range
 * is upserted to the canonical excuse-injected row — status sick/leave,
 * no times, no scan method, no override stamps (a morning tap survives in
 * the append-only scan_events, not on the projection). Rejection leaves
 * attendance untouched.
 *
 * No engine changes were needed: the scan service's excused shield
 * (IgnoredExcused) already keys on sick/leave, and the auto-absent sweep
 * skips students with existing records, so an approved future excuse
 * pre-blocks the sweep for those days naturally.
 */
class ExcuseApprovalService
{
    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * Approve a pending excuse and inject its attendance records.
     *
     * @return bool whether the pending → approved transition ran; false
     *              means the excuse was already resolved (no-op).
     */
    public function approve(Excuse $excuse, User $reviewer, ?string $reviewNote): bool
    {
        return DB::transaction(function () use ($excuse, $reviewer, $reviewNote): bool {
            // Conditional re-fetch under a row lock: two admins reviewing
            // concurrently — exactly one transition wins (no-op on sqlite,
            // real on pgsql).
            $pending = Excuse::query()
                ->whereKey($excuse->id)
                ->where('status', ExcuseStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                return false;
            }

            $pending->update([
                'status' => ExcuseStatus::Approved,
                'review_note' => $reviewNote,
                'reviewed_by_user_id' => $reviewer->id,
            ]);

            $status = $pending->type === ExcuseType::Sick
                ? AttendanceStatus::Sick
                : AttendanceStatus::Leave;

            // Date-only columns: Y-m-d strings only — a date-cast Carbon
            // carries UTC midnight, which drifts a day in a negative-offset
            // school timezone.
            $end = Date::parse($pending->end_date->toDateString());

            for ($day = Date::parse($pending->start_date->toDateString());
                $day->lessThanOrEqualTo($end);
                $day = $day->addDay()) {
                $date = $day->toDateString();

                if (! $this->settings->isSchoolDay($date)) {
                    continue;
                }

                $this->upsertExcusedDay($pending->student_id, $date, $status);
            }

            return true;
        });
    }

    /**
     * Reject a pending excuse — a stamp only; attendance stays as-is.
     *
     * @return bool whether the pending → rejected transition ran.
     */
    public function reject(Excuse $excuse, User $reviewer, ?string $reviewNote): bool
    {
        $updated = Excuse::query()
            ->whereKey($excuse->id)
            ->where('status', ExcuseStatus::Pending->value)
            ->update([
                'status' => ExcuseStatus::Rejected,
                'review_note' => $reviewNote,
                'reviewed_by_user_id' => $reviewer->id,
            ]);

        return $updated === 1;
    }

    /**
     * Canonical excuse-injected row for one (student, date): full excused
     * overwrite — an existing record (swept absence, manual override,
     * morning tap) is replaced, not merged.
     */
    private function upsertExcusedDay(int $studentId, string $date, AttendanceStatus $status): void
    {
        $values = [
            'status' => $status,
            'checked_in_at' => null,
            'checked_out_at' => null,
            'scan_method' => null,
            'override_by_user_id' => null,
            'notes' => null,
        ];

        try {
            Attendance::query()->updateOrCreate(
                ['student_id' => $studentId, 'date' => $date],
                $values,
            );
        } catch (UniqueConstraintViolationException) {
            // Scanner/sweep won the (student_id, date) insert — re-fetch
            // the winner and overwrite it (same create-once rule as the
            // scan service and bulk-present).
            Attendance::query()
                ->where('student_id', $studentId)
                ->whereDate('date', $date)
                ->firstOrFail()
                ->update($values);
        }
    }
}
