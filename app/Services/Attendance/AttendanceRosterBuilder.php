<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SchoolSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class AttendanceRosterBuilder
{
    public function __construct(
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Build the attendance exception dashboard payload for the specified user, class, and date.
     *
     * @param  Collection<int, int>  $classIds
     * @return array{
     *     classes: EloquentCollection<int, SchoolClass>,
     *     classId: int,
     *     date: string,
     *     rows: Collection<int, array{
     *         student_id: int,
     *         full_name: string,
     *         student_number: string|null,
     *         attendance: array{
     *             status: string,
     *             checked_in_at: string|null,
     *             checked_out_at: string|null,
     *             scan_method: string|null,
     *             notes: string|null
     *         }|null
     *     }>
     * }
     */
    public function build(Collection $classIds, ?int $requestedClassId, string $date): array
    {
        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $classId = $requestedClassId !== null && $classIds->contains($requestedClassId)
            ? $requestedClassId
            : (int) $classes->first()?->id;

        $students = Student::query()
            // Roster as of the dashboard's date (spec 07) — a student who
            // moved classes mid-year appears under the right class per day.
            ->whereHas('enrollments', fn (Builder $query) => $query
                ->where('class_id', $classId)
                ->where('started_on', '<=', $date)
                ->where(fn (Builder $query) => $query->whereNull('ended_on')->orWhere('ended_on', '>=', $date)))
            ->with(['attendances' => fn (Builder $query) => $query->whereDate('date', $date)])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_number']);

        $rows = $students->map(fn (Student $student): array => [
            'student_id' => $student->id,
            'full_name' => $student->full_name,
            'student_number' => $student->student_number,
            'attendance' => $this->attendanceRow($student->attendances->first()),
        ]);

        return [
            'classes' => $classes,
            'classId' => $classId,
            'date' => $date,
            'rows' => $rows,
        ];
    }

    /**
     * Times are pre-formatted in the school timezone server-side.
     *
     * @return array{status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null, notes: string|null}|null
     */
    private function attendanceRow(?Attendance $record): ?array
    {
        if ($record === null) {
            return null;
        }

        $tz = $this->settings->timezone();

        return [
            'status' => $record->status->value,
            'checked_in_at' => $record->checked_in_at?->tz($tz)->format('H:i'),
            'checked_out_at' => $record->checked_out_at?->tz($tz)->format('H:i'),
            'scan_method' => $record->scan_method?->value,
            'notes' => $record->notes,
        ];
    }
}
