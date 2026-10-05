<?php

namespace Database\Seeders;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\SchoolSettings;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;

class EmployeeAttendanceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $employees = Employee::active()->orderBy('id')->get();
        if ($employees->isEmpty()) {
            return;
        }

        $settings = app(SchoolSettings::class);
        $employeeCalendar = app(EmployeeCalendar::class);
        $tz = $settings->timezone();
        $today = $settings->now()->startOfDay();

        $recordEmployee = function (
            Employee $employee,
            string $date,
            EmployeeAttendanceStatus $status,
            ?string $in = null,
            ?string $out = null,
            ?ScanMethod $method = null,
            ?int $overrideBy = null,
            ?string $notes = null
        ) use ($tz, $settings): void {
            $checkedIn = $in === null ? null : Date::parse("{$date} {$in}", $tz)->tz('UTC');
            $checkedOut = $out === null ? null : Date::parse("{$date} {$out}", $tz)->tz('UTC');

            $lateMinutes = 0;
            if ($checkedIn !== null) {
                $localIn = $checkedIn->copy()->tz($tz);
                $startTime = $settings->staffStartTimeOn($localIn);
                if ($localIn->greaterThan($startTime)) {
                    $lateMinutes = (int) ceil($startTime->diffInMinutes($localIn, false));
                }
            }

            $earlyLeaveMinutes = 0;
            if ($checkedOut !== null) {
                $localOut = $checkedOut->copy()->tz($tz);
                $endTime = $settings->staffEndTimeOn($localOut);
                $earlyLeaveMinutes = max(0, (int) ceil($localOut->diffInMinutes($endTime, false)));
            }

            EmployeeAttendance::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'date' => $date,
                ],
                [
                    'status' => $status,
                    'checked_in_at' => $checkedIn,
                    'checked_out_at' => $checkedOut,
                    'late_minutes' => $lateMinutes,
                    'early_leave_minutes' => $earlyLeaveMinutes,
                    'scan_method' => $method ?? ($checkedIn !== null ? ScanMethod::Rfid : null),
                    'override_by_user_id' => $overrideBy,
                    'overridden_at' => $overrideBy !== null ? now() : null,
                    'notes' => $notes,
                ]
            );
        };

        $dayNumber = 0;

        for ($day = $today->copy()->startOfMonth(); $day->lessThan($today); $day = $day->addDay()) {
            if ($day->isWeekend() || ! $settings->isSchoolDay($day->toDateString())) {
                continue;
            }

            $dayNumber++;
            $date = $day->toDateString();

            foreach ($employees as $empIndex => $employee) {
                if (! $employeeCalendar->isExpected($employee, $date)) {
                    continue;
                }

                if ($empIndex === 2 && in_array($dayNumber, [6, 7], true)) {
                    $recordEmployee($employee, $date, EmployeeAttendanceStatus::Sick, null, null, null, null, 'Sakit demam');

                    continue;
                }

                $empRoll = ($empIndex * 5 + $dayNumber * 2) % 11;

                match (true) {
                    $empRoll === 0 => $recordEmployee($employee, $date, EmployeeAttendanceStatus::Absent),
                    $empRoll === 1 => $recordEmployee($employee, $date, EmployeeAttendanceStatus::Late, sprintf('07:%02d', 10 + ($empIndex * 4) % 15), '14:15'),
                    $empRoll === 2 && $dayNumber === 1 => $recordEmployee($employee, $date, EmployeeAttendanceStatus::Present, '06:50', null),
                    default => $recordEmployee($employee, $date, EmployeeAttendanceStatus::Present, sprintf('06:%02d', 40 + ($empIndex * 3) % 18), '14:10'),
                };
            }
        }

        if ($settings->isSchoolDay($today->toDateString())) {
            $staffMorning = [
                ['Budi Santoso', EmployeeAttendanceStatus::Present, '06:48', null],
                ['Siti Aminah', EmployeeAttendanceStatus::Late, '07:18', null],
                ['Hendra Setiawan', EmployeeAttendanceStatus::Present, '06:42', null],
                ['Agus Priyono', EmployeeAttendanceStatus::Leave, null, null],
            ];

            foreach ($staffMorning as [$empName, $status, $in, $out]) {
                $employee = $employees->firstWhere('name', $empName);
                if ($employee !== null && $employeeCalendar->isExpected($employee, $today->toDateString())) {
                    $recordEmployee($employee, $today->toDateString(), $status, $in, $out);
                }
            }
        }
    }
}
