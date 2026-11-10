<?php

namespace App\Services\Staff;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use App\Services\Timetable\TodaySchedule;

class StaffAttendanceSummaryService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly EmployeeCalendar $calendar,
        private readonly TodaySchedule $todaySchedule,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * Build the daily overview rows and summary metrics for staff attendance.
     *
     * @return array{
     *     date: string,
     *     is_school_day: bool,
     *     filter: string,
     *     rows: list<array<string, mixed>>,
     *     summary: array{
     *         expected: int,
     *         arrived: int,
     *         late: int,
     *         not_yet_arrived: int,
     *         on_leave: int,
     *         absent: int,
     *         no_checkout: int
     *     },
     *     staff_start_time: string,
     *     staff_end_time: string,
     *     statuses: list<array{value: string, label: string}>
     * }
     */
    public function getDailyOverview(string $date, string $filter = 'all'): array
    {
        $tz = $this->settings->timezone();
        $todayDate = $this->settings->todayDate();
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

        return [
            'date' => $date,
            'is_school_day' => $isSchoolDay,
            'filter' => $filter,
            'rows' => $rows,
            'summary' => $summary,
            'staff_start_time' => $this->settings->staffStartTime(),
            'staff_end_time' => $this->settings->staffEndTime(),
            'statuses' => array_map(fn (EmployeeAttendanceStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ], EmployeeAttendanceStatus::cases()),
        ];
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
}
