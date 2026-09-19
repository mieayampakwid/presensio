<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;

#[Signature('attendance:mark-absences')]
#[Description('Mark students without an attendance record today as absent')]
class MarkAbsencesCommand extends Command
{
    /**
     * Auto-absent sweep (spec 03 §Requirements 3). Creates records with a
     * null scan_method — the system-generated marker. Attendance::created
     * is spec 05's future notification trigger point; nothing fires today.
     */
    public function handle(SchoolSettings $settings): int
    {
        $today = $settings->todayDate();

        if (! $settings->isSchoolDay($today)) {
            $this->info("Non-school day ({$today}) — skipped.");

            return self::SUCCESS;
        }

        $students = Student::query()
            ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $today))
            ->get(['id']);

        foreach ($students as $student) {
            // A student tapping a scanner mid-sweep wins the unique
            // (student_id, date) insert — the tap already created their
            // record, so skip them (same create-once rule as the scan
            // service).
            try {
                Attendance::create([
                    'student_id' => $student->id,
                    'date' => $today,
                    'status' => 'absent',
                    'scan_method' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        $this->info("Marked {$students->count()} students absent for {$today}.");

        return self::SUCCESS;
    }
}
