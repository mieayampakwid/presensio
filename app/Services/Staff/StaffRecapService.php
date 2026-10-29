<?php

namespace App\Services\Staff;

use App\Concerns\WritesCsv;
use App\Enums\EmployeeAttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Services\Attendance\EmployeeCalendar;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only monthly recap for staff attendance (spec 16 §Req 6): one row
 * per active employee over a calendar month, plus the streamed CSV that
 * mirrors the screen column-for-column. Plain queries on purpose — no
 * caching layer at single-school scale.
 *
 * Rate (spec 16 AC-16-09): (hadir + dinas luar) / expected days × 100,
 * where hadir counts present + late. Taps on non-expected days are
 * "extra" days: they never raise the denominator.
 */
class StaffRecapService
{
    use WritesCsv;

    public function __construct(
        private readonly EmployeeCalendar $calendar,
        private readonly SchoolSettings $settings,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * Monthly recap rows for every active employee, plus aggregate summary.
     *
     * @return array{month: string, start_date: string, end_date: string, rows: list<array<string, mixed>>, summary: array<string, float|int>}
     */
    public function monthlyRecap(CarbonInterface|string $month): array
    {
        $tz = $this->settings->timezone();
        $start = $this->resolveMonthStart($month, $tz);
        $end = $start->copy()->endOfMonth();
        $monthKey = $start->format('Y-m');

        $employees = Employee::query()->active()->orderBy('name')->get();

        $records = EmployeeAttendance::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereIn('employee_id', $employees->pluck('id'))
            ->with('shift')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($days) => $days->keyBy(fn (EmployeeAttendance $record) => $record->date->toDateString()));

        $rows = [];
        $summary = $this->emptySummary();

        foreach ($employees as $employee) {
            $row = $this->recapRow($employee, $records->get($employee->id), $start, $end);

            $rows[] = $row;

            foreach (['expected_days', 'extra_days', 'hadir', 'terlambat_count', 'terlambat_minutes', 'pulang_cepat_count', 'pulang_cepat_minutes', 'tidak_absen_pulang', 'sakit', 'izin', 'cuti', 'dinas_luar', 'alpa'] as $key) {
                $summary[$key] += $row[$key];
            }
            $summary['employees']++;
        }

        $summary['rate'] = $this->rate($summary['hadir'], $summary['dinas_luar'], $summary['expected_days']);

        return [
            'month' => $monthKey,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    /**
     * CSV mirroring the recap screen: UTF-8 BOM, one row per employee,
     * columns in the same order as the table.
     */
    public function exportCsv(CarbonInterface|string $month): StreamedResponse
    {
        $recap = $this->monthlyRecap($month);
        $filename = sprintf('rekap-kehadiran-staf-%s.csv', $recap['month']);

        return response()->streamDownload(function () use ($recap): void {
            $out = $this->openCsv();

            fputcsv($out, [
                'NIP/NIK',
                'Nama',
                'Hari Kerja (Expected)',
                'Hadir',
                'Terlambat (kali)',
                'Terlambat (menit)',
                'Pulang Cepat (kali)',
                'Pulang Cepat (menit)',
                'Tidak Absen Pulang',
                'Sakit',
                'Izin',
                'Cuti',
                'Dinas Luar',
                'Alpa',
                'Hari Tambahan (Extra)',
                'Persentase Kehadiran (%)',
            ]);

            foreach ($recap['rows'] as $row) {
                fputcsv($out, [
                    $row['employee_number'] ?? '',
                    $row['name'],
                    $row['expected_days'],
                    $row['hadir'],
                    $row['terlambat_count'],
                    $row['terlambat_minutes'],
                    $row['pulang_cepat_count'],
                    $row['pulang_cepat_minutes'],
                    $row['tidak_absen_pulang'],
                    $row['sakit'],
                    $row['izin'],
                    $row['cuti'],
                    $row['dinas_luar'],
                    $row['alpa'],
                    $row['extra_days'],
                    $row['rate'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Walk the calendar month once for the employee, accumulating expected
     * days, extra (non-expected) taps and the per-status metrics.
     *
     * @param  Collection<int|string, EmployeeAttendance>  $records  attendance keyed by Y-m-d
     * @return array<string, mixed>
     */
    private function recapRow(Employee $employee, ?Collection $records, CarbonInterface $start, CarbonInterface $end): array
    {
        $row = [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'employee_number' => $employee->employee_number,
            'position' => $employee->position,
            'is_teacher' => $employee->isTeacher(),
            'expected_days' => 0,
            'extra_days' => 0,
            'hadir' => 0,
            'terlambat_count' => 0,
            'terlambat_minutes' => 0,
            'pulang_cepat_count' => 0,
            'pulang_cepat_minutes' => 0,
            'tidak_absen_pulang' => 0,
            'sakit' => 0,
            'izin' => 0,
            'cuti' => 0,
            'dinas_luar' => 0,
            'alpa' => 0,
            'rate' => 0.0,
        ];

        $current = $start->copy();

        while ($current->lessThanOrEqualTo($end)) {
            $isExpected = $this->calendar->isExpected($employee, $current);

            if ($isExpected) {
                $row['expected_days']++;
            }

            $record = $records?->get($current->toDateString());

            if ($record !== null) {
                if (! $isExpected) {
                    $row['extra_days']++;
                }

                $this->accumulate($row, $record);
            }

            $current = $current->addDay();
        }

        $row['rate'] = $this->rate($row['hadir'], $row['dinas_luar'], $row['expected_days']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function accumulate(array &$row, EmployeeAttendance $record): void
    {
        $status = $record->status;

        if ($status === EmployeeAttendanceStatus::Present || $status === EmployeeAttendanceStatus::Late) {
            $row['hadir']++;
        }

        if ($status === EmployeeAttendanceStatus::Late) {
            $row['terlambat_count']++;
            $row['terlambat_minutes'] += $record->late_minutes;
        }

        if ($status === EmployeeAttendanceStatus::Sick) {
            $row['sakit']++;
        }

        if ($status === EmployeeAttendanceStatus::Leave) {
            $row['izin']++;
        }

        if ($status === EmployeeAttendanceStatus::AnnualLeave) {
            $row['cuti']++;
        }

        if ($status === EmployeeAttendanceStatus::OfficialDuty) {
            $row['dinas_luar']++;
        }

        if ($status === EmployeeAttendanceStatus::Absent) {
            $row['alpa']++;
        }

        if ($record->early_leave_minutes > 0) {
            $row['pulang_cepat_count']++;
            $row['pulang_cepat_minutes'] += $record->early_leave_minutes;
        }

        if (in_array($status, [EmployeeAttendanceStatus::Present, EmployeeAttendanceStatus::Late], true)
            && $record->checked_out_at === null
            && $this->shifts->windowFor($record->shift, $record->date->toDateString())[1]->isPast()) {
            $row['tidak_absen_pulang']++;
        }
    }

    /**
     * AC-16-09: (hadir + dinas luar) / expected days × 100, one decimal;
     * 0.0 when nothing is expected.
     */
    private function rate(int $hadir, int $dinasLuar, int $expectedDays): float
    {
        return $expectedDays > 0
            ? round((($hadir + $dinasLuar) / $expectedDays) * 100, 1)
            : 0.0;
    }

    /**
     * @return array<string, float|int>
     */
    private function emptySummary(): array
    {
        return [
            'employees' => 0,
            'expected_days' => 0,
            'extra_days' => 0,
            'hadir' => 0,
            'terlambat_count' => 0,
            'terlambat_minutes' => 0,
            'pulang_cepat_count' => 0,
            'pulang_cepat_minutes' => 0,
            'tidak_absen_pulang' => 0,
            'sakit' => 0,
            'izin' => 0,
            'cuti' => 0,
            'dinas_luar' => 0,
            'alpa' => 0,
            'rate' => 0.0,
        ];
    }

    /**
     * First day of the requested month, evaluated in the school timezone.
     */
    private function resolveMonthStart(CarbonInterface|string $month, string $tz): CarbonInterface
    {
        if (is_string($month)) {
            return Date::parse($month.'-01', $tz)->startOfMonth();
        }

        return $month->copy()->tz($tz)->startOfMonth();
    }
}
