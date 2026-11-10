<?php

namespace App\Services\Staff;

use App\Enums\ScanMethod;
use App\Models\EmployeeAttendance;
use App\Models\Shift;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class StaffAttendanceOverrideService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * Perform a manual override / upsert for staff attendance.
     *
     * @param  array<string, mixed>  $validated
     */
    public function override(array $validated, ?Shift $shift, int $overrideByUserId): EmployeeAttendance
    {
        $tz = $this->settings->timezone();
        /** @var string $date */
        $date = $validated['date'];

        return DB::transaction(function () use ($validated, $tz, $date, $shift, $overrideByUserId): EmployeeAttendance {
            $existing = EmployeeAttendance::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereDate('date', $date)
                ->first();

            $old = $existing?->toArray();

            $checkedInInstant = ! empty($validated['checked_in_at'])
                ? Date::parse("{$date} {$validated['checked_in_at']}", $tz)->tz('UTC')
                : null;

            // On a cross-midnight shift a checkout time earlier than the
            // check-in parses as the next calendar day (spec 20 §Req 8).
            $checkoutDate = $date;

            if ($shift?->crosses_midnight
                && ! empty($validated['checked_in_at'])
                && ! empty($validated['checked_out_at'])
                && $validated['checked_out_at'] < $validated['checked_in_at']
            ) {
                $checkoutDate = Date::parse($date)->addDay()->toDateString();
            }

            $checkedOutInstant = ! empty($validated['checked_out_at'])
                ? Date::parse("{$checkoutDate} {$validated['checked_out_at']}", $tz)->tz('UTC')
                : null;

            $lateMinutes = 0;
            if ($checkedInInstant !== null) {
                $localIn = $checkedInInstant->copy()->tz($tz);
                $staffStartTime = $this->shifts->windowFor($shift, $date)[0];
                if ($localIn->greaterThan($staffStartTime)) {
                    $lateMinutes = (int) ceil($staffStartTime->diffInMinutes($localIn, false));
                }
            }

            $earlyLeaveMinutes = 0;
            if ($checkedOutInstant !== null) {
                $localOut = $checkedOutInstant->copy()->tz($tz);
                $staffEndTime = $this->shifts->windowFor($shift, $date)[1];
                $earlyLeaveMinutes = max(0, (int) ceil($localOut->diffInMinutes($staffEndTime, false)));
            }

            $record = EmployeeAttendance::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'date' => $date,
                ],
                [
                    'status' => $validated['status'],
                    'checked_in_at' => $checkedInInstant,
                    'checked_out_at' => $checkedOutInstant,
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => $earlyLeaveMinutes,
                    'shift_id' => $shift?->id,
                    'scan_method' => ScanMethod::ManualOverride,
                    'override_by_user_id' => $overrideByUserId,
                    'overridden_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            $action = $record->wasRecentlyCreated ? 'created' : 'updated';
            $record->recordAudit($action, $old, $record->toArray(), 'manual_override');

            return $record;
        });
    }
}
