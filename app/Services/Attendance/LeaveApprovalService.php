<?php

namespace App\Services\Attendance;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeaveRequest;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Applies the principal/admin review decision for an employee leave
 * request (spec 16 §Req 5). Approval is the only path that writes
 * employee attendance: every *expected* day inside the range (school day
 * AND a working day for that employee, via EmployeeCalendar) is upserted
 * to the leave-type row — no times, no scan method, no notes. An existing
 * present/late record on a day is left alone: the tap already proved the
 * person showed up, and the leave row must not erase that evidence.
 * Rejection is a stamp only; attendance stays as-is.
 */
class LeaveApprovalService
{
    public function __construct(
        private readonly EmployeeCalendar $calendar,
    ) {}

    /**
     * Approve a pending leave request and inject attendance rows for the
     * expected days in its range.
     *
     * @return bool whether the pending → approved transition ran; false
     *              means the request was already resolved (no-op).
     */
    public function approve(EmployeeLeaveRequest $request, User $reviewer, ?string $reviewNote): bool
    {
        return DB::transaction(function () use ($request, $reviewer, $reviewNote): bool {
            // Conditional re-fetch under a row lock: two reviewers acting
            // concurrently — exactly one transition wins (no-op on sqlite,
            // real on pgsql).
            $pending = EmployeeLeaveRequest::query()
                ->whereKey($request->id)
                ->where('status', LeaveRequestStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                return false;
            }

            $pending->update([
                'status' => LeaveRequestStatus::Approved,
                'review_note' => $reviewNote,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $status = $this->attendanceStatusFor($pending->type);

            // Date-only columns: Y-m-d strings only — a date-cast Carbon
            // carries UTC midnight, which drifts a day in a negative-offset
            // school timezone.
            foreach (
                $this->calendar->expectedDatesInRange(
                    $pending->employee,
                    $pending->start_date->toDateString(),
                    $pending->end_date->toDateString(),
                ) as $date
            ) {
                $existing = EmployeeAttendance::query()
                    ->where('employee_id', $pending->employee_id)
                    ->where('date', $date)
                    ->first();

                // A morning tap outranks the approved leave: present/late
                // rows stay exactly as the scanner wrote them.
                if ($existing !== null && in_array($existing->status, [
                    EmployeeAttendanceStatus::Present,
                    EmployeeAttendanceStatus::Late,
                ], true)) {
                    continue;
                }

                $this->upsertLeaveDay($pending->employee_id, $date, $status);
            }

            return true;
        });
    }

    /**
     * Reject a pending leave request — a stamp only; attendance stays
     * as-is.
     *
     * @return bool whether the pending → rejected transition ran.
     */
    public function reject(EmployeeLeaveRequest $request, User $reviewer, string $reviewNote): bool
    {
        return DB::transaction(function () use ($request, $reviewer, $reviewNote): bool {
            $pending = EmployeeLeaveRequest::query()
                ->whereKey($request->id)
                ->where('status', LeaveRequestStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($pending === null) {
                return false;
            }

            $pending->update([
                'status' => LeaveRequestStatus::Rejected,
                'review_note' => $reviewNote,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            return true;
        });
    }

    private function attendanceStatusFor(LeaveType $type): EmployeeAttendanceStatus
    {
        return match ($type) {
            LeaveType::Sick => EmployeeAttendanceStatus::Sick,
            LeaveType::Leave => EmployeeAttendanceStatus::Leave,
            LeaveType::AnnualLeave => EmployeeAttendanceStatus::AnnualLeave,
            LeaveType::OfficialDuty => EmployeeAttendanceStatus::OfficialDuty,
        };
    }

    /**
     * Canonical leave-injected row for one (employee, date): no times, no
     * scan method, no notes — the projection records the excused status
     * only (the scan service's excused shield turns taps on such days into
     * ignored_excused).
     */
    private function upsertLeaveDay(int $employeeId, string $date, EmployeeAttendanceStatus $status): void
    {
        $values = [
            'status' => $status,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'scan_method' => null,
            'notes' => null,
        ];

        try {
            EmployeeAttendance::query()->updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $date],
                $values,
            );
        } catch (UniqueConstraintViolationException) {
            // Scanner/sweep won the (employee_id, date) insert — re-fetch
            // the winner and overwrite it (same create-once rule as the
            // scan service and excuse approval).
            EmployeeAttendance::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->firstOrFail()
                ->update($values);
        }
    }
}
