<?php

namespace App\Console\Commands;

use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\SchoolSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

#[Signature('staff-attendance:mark-absences {--date= : Specific date to sweep (defaults to school today)}')]
#[Description('Mark expected active employees without an attendance record today as absent')]
class MarkStaffAbsencesCommand extends Command
{
    /**
     * Auto-absent sweep for staff (spec 16 §Decisions, AC-16-05).
     * Creates records with status 'absent', null scan_method, and no scan_events.
     */
    public function handle(SchoolSettings $settings, EmployeeCalendar $calendar): int
    {
        $targetDate = (string) ($this->option('date') ?: $settings->todayDate());

        if (! $settings->isSchoolDay($targetDate)) {
            $this->info("Non-school day ({$targetDate}) — skipped.");

            return self::SUCCESS;
        }

        $candidates = Employee::query()
            ->active()
            ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $targetDate))
            ->get();

        $markedCount = 0;

        foreach ($candidates as $employee) {
            if (! $calendar->isExpected($employee, $targetDate)) {
                continue;
            }

            try {
                EmployeeAttendance::create([
                    'employee_id' => $employee->id,
                    'date' => $targetDate,
                    'status' => EmployeeAttendanceStatus::Absent,
                    'checked_in_at' => null,
                    'checked_out_at' => null,
                    'late_minutes' => 0,
                    'early_leave_minutes' => 0,
                    'scan_method' => null,
                ]);

                $markedCount++;
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        $this->info("Marked {$markedCount} employees absent for {$targetDate}.");

        return self::SUCCESS;
    }
}
