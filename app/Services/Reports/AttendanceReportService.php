<?php

namespace App\Services\Reports;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\NonSchoolDay;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only report assembly for spec 06: student/class summaries over a
 * date range plus the live presence board. Plain queries on purpose — no
 * caching layer at single-school scale.
 *
 * Attendance rate (locked 2026-09-20): (present + late) / (present + late
 * + absent) × 100 — sick/leave are their own counts and stay out of the
 * denominator unless the report's include-excused switch is on. Rate is
 * null ("—") when the denominator is 0.
 */
class AttendanceReportService
{
    private const STATUS_ORDER = ['present', 'late', 'absent', 'sick', 'leave'];

    /** Matches STATUS_BADGES labels in resources/js/lib/attendance.ts. */
    private const STATUS_LABELS = [
        'present' => 'Present',
        'late' => 'Late',
        'absent' => 'Absent',
        'sick' => 'Sick',
        'leave' => 'Leave',
    ];

    /** Matches METHOD_LABELS in resources/js/lib/attendance.ts. */
    private const METHOD_LABELS = [
        'rfid' => 'RFID',
        'dynamic_qr' => 'QR',
        'manual_override' => 'Manual override',
    ];

    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * @param  array<string, int>  $counts
     */
    public function rate(array $counts, bool $includeExcused): ?float
    {
        $denominator = $counts['present'] + $counts['late'] + $counts['absent'];

        if ($includeExcused) {
            $denominator += $counts['sick'] + $counts['leave'];
        }

        if ($denominator === 0) {
            return null;
        }

        return round((($counts['present'] + $counts['late']) / $denominator) * 100, 1);
    }

    /**
     * Report date range: defaults to the full current month (school
     * timezone); any missing or invalid bound falls back to the month, an
     * inverted range resets to it, and anything wider than 366 days
     * clamps to from + 365 days (spec 06 §Decisions).
     *
     * @return array{CarbonInterface, CarbonInterface}
     */
    public function resolveRange(?string $fromValue, ?string $toValue): array
    {
        $tz = $this->settings->timezone();
        $now = $this->settings->now();

        $defaultFrom = $now->copy()->startOfMonth()->startOfDay();
        $defaultTo = $now->copy()->endOfMonth()->startOfDay();

        $from = $fromValue !== null && $fromValue !== '' && Date::hasFormat($fromValue, 'Y-m-d')
            ? Date::parse($fromValue, $tz)->startOfDay()
            : $defaultFrom;

        $to = $toValue !== null && $toValue !== '' && Date::hasFormat($toValue, 'Y-m-d')
            ? Date::parse($toValue, $tz)->startOfDay()
            : $defaultTo;

        if ($from->gt($to)) {
            return [$defaultFrom, $defaultTo];
        }

        $cap = $from->copy()->addDays(365);

        return [$from, $to->greaterThan($cap) ? $cap : $to];
    }

    /**
     * Per-status counts, rate, and the day-by-day record list for one
     * student. Notes/override attribution are never included (spec 03
     * privacy carve-out — reports serve students and parents too).
     *
     * @return array{counts: array<string, int>, rate: float|null, records: array<int, array{date: string, status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null}>}
     */
    public function studentReport(Student $student, CarbonInterface $from, CarbonInterface $to, bool $includeExcused): array
    {
        $records = $student->attendances()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $counts = $this->emptyCounts();

        foreach ($records as $record) {
            $counts[$record->status->value]++;
        }

        return [
            'counts' => $counts,
            'rate' => $this->rate($counts, $includeExcused),
            'records' => $records->map(fn (Attendance $record) => $this->recordRow($record))->all(),
        ];
    }

    /**
     * One grid row: per-status counts, rate, and the day → status map for
     * a single student.
     *
     * @param  array<int, list<Attendance>>  $recordsByStudent
     * @return array{id: int, student_number: string|null, full_name: string, counts: array<string, int>, rate: float|null, statuses: array<string, string>}
     */
    private function classRow(Student $student, array $recordsByStudent, bool $includeExcused): array
    {
        $counts = $this->emptyCounts();
        $statuses = [];

        foreach ($recordsByStudent[$student->id] ?? [] as $record) {
            $counts[$record->status->value]++;
            $statuses[$record->date->toDateString()] = $record->status->value;
        }

        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'full_name' => $student->full_name,
            'counts' => $counts,
            'rate' => $this->rate($counts, $includeExcused),
            'statuses' => $statuses,
        ];
    }

    /**
     * Students × dates grid data for one class. Only days with a record
     * appear in `statuses` — blanks are implicit and never counted.
     *
     * @return array{dates: list<string>, non_school_dates: list<string>, rows: array<int, array{id: int, student_number: string|null, full_name: string, counts: array<string, int>, rate: float|null, statuses: array<string, string>}>}
     */
    public function classReport(SchoolClass $class, CarbonInterface $from, CarbonInterface $to, bool $includeExcused): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $students = $class->students()->orderBy('full_name')->get(['id', 'full_name', 'student_number']);

        $recordsByStudent = [];

        foreach (Attendance::query()
            ->whereIn('student_id', $students->pluck('id'))
            ->whereBetween('date', [$fromDate, $toDate])
            ->get(['student_id', 'date', 'status']) as $record) {
            $recordsByStudent[$record->student_id][] = $record;
        }

        $dates = [];
        $nonSchoolDates = [];

        for ($day = $from->copy()->startOfDay(); $day->toDateString() <= $toDate; $day = $day->addDay()) {
            $dates[] = $day->toDateString();

            if ($day->isWeekend()) {
                $nonSchoolDates[] = $day->toDateString();
            }
        }

        foreach (NonSchoolDay::query()->whereBetween('date', [$fromDate, $toDate])->pluck('date') as $date) {
            $nonSchoolDates[] = $date->toDateString();
        }

        $rows = $students
            ->map(fn (Student $student) => $this->classRow($student, $recordsByStudent, $includeExcused))
            ->all();

        return [
            'dates' => $dates,
            'non_school_dates' => array_values(array_unique($nonSchoolDates)),
            'rows' => $rows,
        ];
    }

    /**
     * Today's presence board over the scoped classes. Buckets per class:
     * in-building (present/late without a checkout — counts bulk-marked
     * presents so the evacuation headcount errs high, never low),
     * checked-out, and not-yet-checked-in (no record yet, or sick/leave/
     * absent — listed with their status for the drill-down). Totals are
     * folded into the payload so a partial `only: ['board']` reload
     * refreshes everything at once.
     *
     * @param  Collection<int, int>  $classIds
     * @return array{classes: array<int, array{id: int, name: string, enrolled: int, in_building: int, checked_out: int, not_checked_in: int, not_in: list<array{full_name: string, status: string|null}>}>, totals: array{enrolled: int, in_building: int, checked_out: int, not_checked_in: int}|null, as_of: string}
     */
    public function board(Collection $classIds, User $viewer): array
    {
        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->with(['students' => fn (Builder $query) => $query->orderBy('full_name')])
            ->get(['id', 'name']);

        $records = Attendance::query()
            ->whereIn('student_id', $classes->flatMap(fn (SchoolClass $class) => $class->students->pluck('id')))
            ->whereDate('date', $this->settings->todayDate())
            ->get(['student_id', 'status', 'checked_out_at'])
            ->keyBy('student_id');

        $totals = ['enrolled' => 0, 'in_building' => 0, 'checked_out' => 0, 'not_checked_in' => 0];

        $classes = $classes
            ->map(function (SchoolClass $class) use ($records, &$totals) {
                $inBuilding = 0;
                $checkedOut = 0;
                $notIn = [];

                foreach ($class->students as $student) {
                    $record = $records->get($student->id);
                    $status = $record?->status->value;

                    if ($status === 'present' || $status === 'late') {
                        if ($record->checked_out_at !== null) {
                            $checkedOut++;
                        } else {
                            $inBuilding++;
                        }

                        continue;
                    }

                    $notIn[] = ['full_name' => $student->full_name, 'status' => $status];
                }

                $totals['enrolled'] += $class->students->count();
                $totals['in_building'] += $inBuilding;
                $totals['checked_out'] += $checkedOut;
                $totals['not_checked_in'] += count($notIn);

                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'enrolled' => $class->students->count(),
                    'in_building' => $inBuilding,
                    'checked_out' => $checkedOut,
                    'not_checked_in' => count($notIn),
                    'not_in' => $notIn,
                ];
            })
            ->values()
            ->all();

        return [
            'classes' => $classes,
            'totals' => $viewer->role === UserRole::Admin ? $totals : null,
            'as_of' => $this->settings->now()->format('H:i'),
        ];
    }

    /**
     * CSV mirroring the student report screen: summary block, then the
     * day-by-day list. The rate cell reflects the include-excused switch.
     */
    public function studentCsv(Student $student, CarbonInterface $from, CarbonInterface $to, bool $includeExcused): StreamedResponse
    {
        $report = $this->studentReport($student, $from, $to, $includeExcused);
        $filename = sprintf(
            'student-report-%s-%s-to-%s.csv',
            $student->student_number ?? $student->id,
            $from->toDateString(),
            $to->toDateString(),
        );

        return response()->streamDownload(function () use ($student, $report, $from, $to, $includeExcused): void {
            $out = $this->openCsv();

            fputcsv($out, ['Student Report', $student->full_name]);
            fputcsv($out, ['NIS', $student->student_number ?? '']);
            fputcsv($out, ['Period', $from->toDateString(), 'to', $to->toDateString()]);
            fputcsv($out, ['Include sick/leave in rate', $includeExcused ? 'Yes' : 'No']);
            fputcsv($out, []);

            foreach (self::STATUS_ORDER as $status) {
                fputcsv($out, [self::STATUS_LABELS[$status], $report['counts'][$status]]);
            }

            fputcsv($out, ['Attendance rate', $this->formatRate($report['rate'])]);
            fputcsv($out, []);
            fputcsv($out, ['Date', 'Status', 'Check-in', 'Check-out', 'Method']);

            foreach ($report['records'] as $record) {
                fputcsv($out, [
                    $record['date'],
                    self::STATUS_LABELS[$record['status']],
                    $record['checked_in_at'] ?? '',
                    $record['checked_out_at'] ?? '',
                    $record['scan_method'] === null ? '' : self::METHOD_LABELS[$record['scan_method']],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV mirroring the class report screen: one row per student with the
     * summary counts plus one column per date in the range.
     */
    public function classCsv(SchoolClass $class, CarbonInterface $from, CarbonInterface $to, bool $includeExcused): StreamedResponse
    {
        $report = $this->classReport($class, $from, $to, $includeExcused);
        $filename = sprintf(
            'class-report-%s-%s-to-%s.csv',
            Str::slug($class->name),
            $from->toDateString(),
            $to->toDateString(),
        );

        return response()->streamDownload(function () use ($class, $report, $from, $to, $includeExcused): void {
            $out = $this->openCsv();

            fputcsv($out, ['Class Report', $class->name]);
            fputcsv($out, ['Period', $from->toDateString(), 'to', $to->toDateString()]);
            fputcsv($out, ['Include sick/leave in rate', $includeExcused ? 'Yes' : 'No']);
            fputcsv($out, []);

            $header = ['NIS', 'Name', 'Present', 'Late', 'Absent', 'Sick', 'Leave', 'Rate %'];

            foreach ($report['dates'] as $date) {
                $header[] = Date::parse($date)->format('j/n');
            }

            fputcsv($out, $header);

            foreach ($report['rows'] as $row) {
                $line = [
                    $row['student_number'] ?? '',
                    $row['full_name'],
                    $row['counts']['present'],
                    $row['counts']['late'],
                    $row['counts']['absent'],
                    $row['counts']['sick'],
                    $row['counts']['leave'],
                    $this->formatRate($row['rate']),
                ];

                foreach ($report['dates'] as $date) {
                    $line[] = $row['statuses'][$date] ?? '';
                }

                fputcsv($out, $line);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * UTF-8 BOM first so Excel/Numbers read the file as UTF-8.
     *
     * @return resource
     */
    private function openCsv()
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            throw new RuntimeException('Unable to open the CSV output stream.');
        }

        fwrite($out, "\xEF\xBB\xBF");

        return $out;
    }

    private function formatRate(?float $rate): string
    {
        return $rate === null ? '' : $rate.'%';
    }

    /**
     * Times are pre-formatted in the school timezone server-side.
     *
     * @return array{date: string, status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null}
     */
    private function recordRow(Attendance $record): array
    {
        $tz = $this->settings->timezone();

        return [
            'date' => $record->date->toDateString(),
            'status' => $record->status->value,
            'checked_in_at' => $record->checked_in_at?->tz($tz)->format('H:i'),
            'checked_out_at' => $record->checked_out_at?->tz($tz)->format('H:i'),
            'scan_method' => $record->scan_method?->value,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyCounts(): array
    {
        return ['present' => 0, 'late' => 0, 'absent' => 0, 'sick' => 0, 'leave' => 0];
    }
}
