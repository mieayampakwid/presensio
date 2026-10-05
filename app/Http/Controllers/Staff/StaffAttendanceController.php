<?php

namespace App\Http\Controllers\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpsertStaffAttendanceRequest;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly EmployeeCalendar $calendar,
    ) {}

    /**
     * Daily overview of staff attendance (spec 16 §Req 3).
     */
    public function index(Request $request): Response
    {
        $tz = $this->settings->timezone();
        $todayDate = $this->settings->todayDate();
        $date = $request->string('date')->toString() ?: $todayDate;
        $filter = $request->string('filter')->toString() ?: 'all';
        $isPastDate = $date < $todayDate;

        $isSchoolDay = $this->settings->isSchoolDay($date);

        // Fetch active employees
        $activeEmployees = Employee::query()->active()->orderBy('name')->get();

        // Load existing attendance records for the date
        $records = EmployeeAttendance::query()
            ->whereDate('date', $date)
            ->whereIn('employee_id', $activeEmployees->pluck('id'))
            ->with('overrideBy:id,username')
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $summary = [
            'expected' => 0,
            'arrived' => 0,
            'late' => 0,
            'not_yet_arrived' => 0,
            'on_leave' => 0,
            'absent' => 0,
            'no_checkout' => 0,
        ];

        foreach ($activeEmployees as $emp) {
            $isExpected = $this->calendar->isExpected($emp, $date);
            $record = $records->get($emp->id);

            // Skip employees who are not expected AND have no attendance record that day
            if (! $isExpected && $record === null) {
                continue;
            }

            if ($isExpected) {
                $summary['expected']++;
            }

            $inTime = $record?->checked_in_at?->tz($tz)->format('H:i');
            $outTime = $record?->checked_out_at?->tz($tz)->format('H:i');
            $status = $record?->status;
            $hasIn = $record !== null && $record->checked_in_at !== null;
            $hasOut = $record !== null && $record->checked_out_at !== null;
            $isNoCheckout = $hasIn && ! $hasOut && $isPastDate;

            if ($isNoCheckout) {
                $summary['no_checkout']++;
            }

            if ($record === null) {
                $summary['not_yet_arrived']++;
            } elseif ($status?->isExcused()) {
                $summary['on_leave']++;
            } elseif ($status === EmployeeAttendanceStatus::Absent) {
                $summary['absent']++;
            } elseif ($status === EmployeeAttendanceStatus::Late) {
                $summary['late']++;
                $summary['arrived']++;
            } elseif ($status === EmployeeAttendanceStatus::Present) {
                $summary['arrived']++;
            }

            $row = [
                'employee_id' => $emp->id,
                'name' => $emp->name,
                'employee_number' => $emp->employee_number,
                'position' => $emp->position,
                'is_teacher' => $emp->isTeacher(),
                'is_expected' => $isExpected,
                'status' => $status?->value ?? 'not_yet_arrived',
                'status_label' => $status ? $status->label() : 'Belum Hadir',
                'checked_in_at' => $inTime,
                'checked_out_at' => $outTime,
                'late_minutes' => $record?->late_minutes ?? 0,
                'early_leave_minutes' => $record?->early_leave_minutes ?? 0,
                'no_checkout' => $isNoCheckout,
                'scan_method' => $record?->scan_method?->value,
                'overridden_by' => $record?->overrideBy?->username,
                'notes' => $record?->notes,
            ];

            $include = match ($filter) {
                'not_yet_arrived' => $record === null || ($status === EmployeeAttendanceStatus::Absent && ! $hasIn),
                'late' => $status === EmployeeAttendanceStatus::Late,
                'absent' => $status === EmployeeAttendanceStatus::Absent,
                'on_leave' => $status?->isExcused() ?? false,
                'no_checkout' => $isNoCheckout,
                default => true,
            };

            if ($include) {
                $rows[] = $row;
            }
        }

        return Inertia::render('staff/attendance', [
            'date' => $date,
            'is_school_day' => $isSchoolDay,
            'filter' => $filter,
            'rows' => $rows,
            'summary' => $summary,
            'staff_start_time' => $this->settings->staffStartTime(),
            'staff_end_time' => $this->settings->staffEndTime(),
            'can_override' => $request->user()?->hasRole(UserRole::Admin) ?? false,
            'statuses' => array_map(fn (EmployeeAttendanceStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], EmployeeAttendanceStatus::cases()),
        ]);
    }

    /**
     * Manual override / upsert for staff attendance (spec 16 §Req 3).
     */
    public function upsert(UpsertStaffAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tz = $this->settings->timezone();
        $date = $validated['date'];

        DB::transaction(function () use ($validated, $tz, $date, $request): void {
            $existing = EmployeeAttendance::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereDate('date', $date)
                ->first();

            $old = $existing?->toArray();

            $checkedInInstant = ! empty($validated['checked_in_at'])
                ? Date::parse("{$date} {$validated['checked_in_at']}", $tz)->tz('UTC')
                : null;

            $checkedOutInstant = ! empty($validated['checked_out_at'])
                ? Date::parse("{$date} {$validated['checked_out_at']}", $tz)->tz('UTC')
                : null;

            $lateMinutes = 0;
            if ($checkedInInstant !== null) {
                $localIn = $checkedInInstant->copy()->tz($tz);
                $staffStartTime = $this->settings->staffStartTimeOn($localIn);
                if ($localIn->greaterThan($staffStartTime)) {
                    $lateMinutes = (int) ceil($staffStartTime->diffInMinutes($localIn, false));
                }
            }

            $earlyLeaveMinutes = 0;
            if ($checkedOutInstant !== null) {
                $localOut = $checkedOutInstant->copy()->tz($tz);
                $staffEndTime = $this->settings->staffEndTimeOn($localOut);
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
                    'scan_method' => ScanMethod::ManualOverride,
                    'override_by_user_id' => $request->user()->id,
                    'overridden_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]
            );

            $action = $record->wasRecentlyCreated ? 'created' : 'updated';
            $record->recordAudit($action, $old, $record->toArray(), 'manual_override');
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Presensi pegawai berhasil diperbarui.']);

        return to_route('staff-attendance.index', ['date' => $date]);
    }
}
