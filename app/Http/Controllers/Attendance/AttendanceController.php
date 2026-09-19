<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreBulkAttendanceRequest;
use App\Http\Requests\Attendance\UpsertAttendanceRequest;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teacher/admin exception dashboard (spec 03 §Requirements 4–5). Manual
 * edits never notify anyone (spec 05); creating an absent record here is
 * that notification system's future trigger point, not today's job.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly SchoolSettings $settings) {}

    public function index(Request $request): Response
    {
        $classIds = ClassAccess::classIds($request->user());

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestedClassId = $request->integer('class_id');
        $classId = $classIds->contains($requestedClassId)
            ? $requestedClassId
            : (int) $classes->first()?->id;

        $date = $this->resolveDate($request);

        $students = Student::query()
            ->where('class_id', $classId)
            ->with(['attendances' => fn (Builder $query) => $query->whereDate('date', $date)])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_number']);

        $rows = $students->map(fn (Student $student) => [
            'student_id' => $student->id,
            'full_name' => $student->full_name,
            'student_number' => $student->student_number,
            'attendance' => $this->attendanceRow($student->attendances->first()),
        ]);

        return Inertia::render('attendance/index', [
            'classes' => $classes,
            'filters' => [
                'class_id' => $classId,
                'date' => $date,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * Upsert keyed on (student_id, date) — reconstructs days the scanner
     * was offline, not just repairs existing records.
     */
    public function updateRecord(UpsertAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            Attendance::query()->updateOrCreate(
                [
                    'student_id' => $validated['student_id'],
                    'date' => $validated['date'],
                ],
                [
                    'status' => $validated['status'],
                    'checked_in_at' => isset($validated['checked_in_at'])
                        ? $this->instant($validated['date'], $validated['checked_in_at'])
                        : null,
                    'checked_out_at' => isset($validated['checked_out_at'])
                        ? $this->instant($validated['date'], $validated['checked_out_at'])
                        : null,
                    'scan_method' => ScanMethod::ManualOverride,
                    'override_by_user_id' => auth()->id(),
                    'notes' => $validated['notes'] ?? null,
                ],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Record saved.']);

        return to_route('attendance.index', [
            'class_id' => $request->integer('class_id') ?: null,
            'date' => $validated['date'],
        ]);
    }

    /**
     * Bulk-present fills only the gaps (spec 03 §Requirements 5): students
     * with any record that day — including sick/leave — are never touched.
     */
    public function bulkPresent(StoreBulkAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $count = DB::transaction(function () use ($validated) {
            $students = Student::query()
                ->where('class_id', $validated['class_id'])
                ->whereDoesntHave('attendances', fn (Builder $query) => $query->whereDate('date', $validated['date']))
                ->get(['id']);

            foreach ($students as $student) {
                Attendance::create([
                    'student_id' => $student->id,
                    'date' => $validated['date'],
                    'status' => AttendanceStatus::Present,
                    'scan_method' => ScanMethod::ManualOverride,
                    'override_by_user_id' => auth()->id(),
                    'notes' => 'Bulk marked present',
                ]);
            }

            return $students->count();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Marked {$count} students present."]);

        return to_route('attendance.index', [
            'class_id' => $validated['class_id'],
            'date' => $validated['date'],
        ]);
    }

    private function resolveDate(Request $request): string
    {
        $value = $request->string('date')->toString();

        if ($value !== '' && Date::hasFormat($value, 'Y-m-d')) {
            return Date::parse($value)->toDateString();
        }

        return $this->settings->todayDate();
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
