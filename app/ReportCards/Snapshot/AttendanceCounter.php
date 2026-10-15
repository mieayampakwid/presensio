<?php

namespace App\ReportCards\Snapshot;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Carbon\CarbonInterface;

class AttendanceCounter
{
    /**
     * Count the student's attendance between the given dates (inclusive),
     * across all enrollments (attendance follows the student, not the class).
     * `late` and `present` count as present and are not reported.
     *
     * @return array{sick: int, leave: int, absent: int}
     */
    public function count(int $studentId, CarbonInterface $from, CarbonInterface $to): array
    {
        $counts = Attendance::query()
            ->where('student_id', $studentId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', [AttendanceStatus::Sick, AttendanceStatus::Leave, AttendanceStatus::Absent])
            ->groupBy('status')
            ->selectRaw('status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        return [
            'sick' => (int) ($counts[AttendanceStatus::Sick->value] ?? 0),
            'leave' => (int) ($counts[AttendanceStatus::Leave->value] ?? 0),
            'absent' => (int) ($counts[AttendanceStatus::Absent->value] ?? 0),
        ];
    }
}
