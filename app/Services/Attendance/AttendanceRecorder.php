<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class AttendanceRecorder
{
    public function __construct(
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Upsert an attendance record keyed on (student_id, date).
     *
     * @param  array<string, mixed>  $validated
     */
    public function record(array $validated, ?int $overrideByUserId = null): Attendance
    {
        return DB::transaction(function () use ($validated, $overrideByUserId): Attendance {
            /** @var Attendance $record */
            $record = Attendance::query()->updateOrCreate(
                [
                    'student_id' => $validated['student_id'],
                    'date' => $validated['date'],
                ],
                [
                    'status' => $validated['status'],
                    'checked_in_at' => ! empty($validated['checked_in_at']) && is_string($validated['checked_in_at'])
                        ? $this->instant((string) $validated['date'], $validated['checked_in_at'])
                        : null,
                    'checked_out_at' => ! empty($validated['checked_out_at']) && is_string($validated['checked_out_at'])
                        ? $this->instant((string) $validated['date'], $validated['checked_out_at'])
                        : null,
                    'scan_method' => ScanMethod::ManualOverride,
                    'override_by_user_id' => $overrideByUserId,
                    'notes' => $validated['notes'] ?? null,
                ],
            );

            return $record;
        });
    }

    /**
     * Bulk-present fills only the gaps (spec 03 §Requirements 5): students
     * with any record that day — including sick/leave — are never touched.
     *
     * @param  array<string, mixed>  $validated
     */
    public function bulkPresent(array $validated, ?int $overrideByUserId = null): int
    {
        return DB::transaction(function () use ($validated, $overrideByUserId): int {
            $students = Student::query()
                ->whereHas('enrollments', fn (Builder $query) => $query
                    ->where('class_id', $validated['class_id'])
                    ->where('started_on', '<=', $validated['date'])
                    ->where(fn (Builder $query) => $query->whereNull('ended_on')->orWhere('ended_on', '>=', $validated['date'])))
                ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $validated['date']))
                ->get(['id']);

            foreach ($students as $student) {
                // A student tapping a scanner between the gap query and this
                // create loses the (student_id, date) unique insert — the
                // scanner's record wins and this loop skips them (same
                // create-once rule as the scan service). The savepoint keeps
                // the outer transaction usable after the violation on
                // PostgreSQL.
                try {
                    DB::transaction(fn () => Attendance::create([
                        'student_id' => $student->id,
                        'date' => $validated['date'],
                        'status' => AttendanceStatus::Present,
                        'scan_method' => ScanMethod::ManualOverride,
                        'override_by_user_id' => $overrideByUserId,
                        'notes' => 'Bulk marked present',
                    ]));
                } catch (UniqueConstraintViolationException) {
                    continue;
                }
            }

            return $students->count();
        });
    }

    /**
     * Wall time on the record's date in the school timezone, normalized to
     * UTC for persistence (Eloquent formats datetimes in the value's own
     * timezone).
     */
    private function instant(string $date, string $time): CarbonInterface
    {
        return Date::parse("{$date} {$time}", $this->settings->timezone())->tz('UTC');
    }
}
