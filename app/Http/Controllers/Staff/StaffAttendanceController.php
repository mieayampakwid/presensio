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
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use App\Services\Timetable\TodaySchedule;
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
        private readonly TodaySchedule $todaySchedule,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * Daily overview of staff attendance (spec 16 §Req 3).
     */
    public function index(Request $request): Response
    {
        $tz = $this->settings->timezone();
        $todayDate = $this->settings->todayDate();
        $date = $this->resolveDate($request, $todayDate);
        $filter = $request->string('filter')->toString() ?: 'all';

        $isSchoolDay = $this->settings->isSchoolDay($date);

        // Fetch active employees (teacher preloaded: isTeacher() and the
        // first-period enrichment read it without N+1)
        $activeEmployees = Employee::query()->active()->with('teacher')->orderBy('name')->get();

        // Load existing attendance records for the date (shift preloaded:
        // the no-checkout rule reads the snapshot without N+1)
        $records = EmployeeAttendance::query()
            ->whereDate('date', $date)
            ->whereIn('employee_id', $activeEmployees->pluck('id'))
            ->with('overrideBy:id,username', 'shift')
            ->get()
            ->keyBy('employee_id');

        // Effective shift per employee on the view date (spec 20 §Req 4)
        $resolutions = $this->shifts->resolveMany($activeEmployees, $date);

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
            $notYetArrived = $record === null || ($status === EmployeeAttendanceStatus::Absent && ! $hasIn);

            // Missing checkout once the record's snapshotted window has
            // ended — including today's view (spec 20 §Req 8 / AC-20-15).
            $isNoCheckout = $hasIn && ! $hasOut
                && $this->shifts->windowFor($record->shift, $record->date->toDateString())[1]->isPast();

            $resolution = $resolutions->get($emp->id);
            $effectiveShift = $resolution->shift;

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
                'shift_name' => $effectiveShift?->name,
                'shift_times' => $effectiveShift !== null
                    ? $effectiveShift->start_time->format('H:i').' - '.$effectiveShift->end_time->format('H:i')
                    : null,
                'is_expected' => $isExpected,
                'status' => $status instanceof EmployeeAttendanceStatus ? $status->value : 'not_yet_arrived',
                'status_label' => $status ? $status->label() : 'Belum Hadir',
                'checked_in_at' => $inTime,
                'checked_out_at' => $outTime,
                'late_minutes' => $record !== null ? $record->late_minutes : 0,
                'early_leave_minutes' => $record !== null ? $record->early_leave_minutes : 0,
                'no_checkout' => $isNoCheckout,
                'scan_method' => $record?->scan_method?->value,
                'overridden_by' => $record?->overrideBy?->username,
                'notes' => $record?->notes,
                'first_period' => $this->firstPeriod($emp, $notYetArrived, $date, $todayDate),
            ];

            $include = match ($filter) {
                'not_yet_arrived' => $notYetArrived,
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
        $shift = $request->effectiveShift();

        DB::transaction(function () use ($validated, $tz, $date, $shift, $request): void {
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

    /**
     * The first period of a not-yet-arrived teacher, on today's view only
     * (spec 16 §7: arrange substitutes for their first periods). Null for
     * tendik, arrived teachers, and past-date views.
     *
     * @return array{id: int, class_name: string, title: string, teacher_name: string|null, room: string|null, start_time: string, end_time: string, status: string|null}|null
     */
    private function firstPeriod(Employee $employee, bool $notYetArrived, string $date, string $todayDate): ?array
    {
        if (! $notYetArrived || $date !== $todayDate || $employee->teacher === null) {
            return null;
        }

        return $this->todaySchedule->firstPeriodForTeacher($employee->teacher);
    }

    /**
     * The requested Y-m-d date, or today when missing or malformed.
     */
    private function resolveDate(Request $request, string $todayDate): string
    {
        $value = $request->string('date')->toString();

        if ($value !== '' && Date::hasFormat($value, 'Y-m-d')) {
            return Date::parse($value)->toDateString();
        }

        return $todayDate;
    }
}
