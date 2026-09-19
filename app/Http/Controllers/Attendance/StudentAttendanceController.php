<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Student portal attendance history (read-only). Notes and override
 * attribution stay teacher-facing — rows expose status, times, and method
 * only.
 */
class StudentAttendanceController extends Controller
{
    public function __construct(private readonly SchoolSettings $settings) {}

    public function index(Request $request): Response
    {
        $student = $request->user()->student;

        if ($student === null) {
            return Inertia::render('attendance/my-attendance', [
                'school_timezone' => $this->settings->timezone(),
                'today' => null,
                'records' => null,
            ]);
        }

        $tz = $this->settings->timezone();
        $today = $this->settings->todayDate();

        $todayRecord = $student->attendances()->whereDate('date', $today)->first();

        /** @var LengthAwarePaginator<int, Attendance> $records */
        $records = $student->attendances()
            ->whereDate('date', '!=', $today)
            ->orderByDesc('date')
            ->paginate(15);

        return Inertia::render('attendance/my-attendance', [
            'school_timezone' => $tz,
            'today' => $this->row($todayRecord, $today),
            'records' => $records->through(fn (Attendance $record) => $this->row($record, $record->date->format('Y-m-d'))),
        ]);
    }

    /**
     * Times are pre-formatted in the school timezone server-side.
     *
     * @return array{date: string, status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null}
     */
    private function row(?Attendance $record, string $date): ?array
    {
        if ($record === null) {
            return null;
        }

        $tz = $this->settings->timezone();

        return [
            'date' => $date,
            'status' => $record->status->value,
            'checked_in_at' => $record->checked_in_at?->tz($tz)->format('H:i'),
            'checked_out_at' => $record->checked_out_at?->tz($tz)->format('H:i'),
            'scan_method' => $record->scan_method?->value,
        ];
    }
}
