<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreBulkAttendanceRequest;
use App\Http\Requests\Attendance\UpsertAttendanceRequest;
use App\Services\Attendance\AttendanceRecorder;
use App\Services\Attendance\AttendanceRosterBuilder;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teacher/admin exception dashboard (spec 03 §Requirements 4–5). Manual
 * edits never notify anyone (spec 05); creating an absent record here is
 * the notification system's creation-time trigger (via the observer).
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly AttendanceRosterBuilder $rosterBuilder,
        private readonly AttendanceRecorder $recorder,
    ) {}

    public function index(Request $request): Response
    {
        $classIds = ClassAccess::classIds($request->user());
        $requestedClassId = $request->has('class_id') ? $request->integer('class_id') : null;
        $date = $this->resolveDate($request);

        $data = $this->rosterBuilder->build($classIds, $requestedClassId, $date);

        return Inertia::render('attendance/index', [
            'classes' => $data['classes'],
            'filters' => [
                'class_id' => $data['classId'],
                'date' => $data['date'],
            ],
            'rows' => $data['rows'],
        ]);
    }

    /**
     * Upsert keyed on (student_id, date) — reconstructs days the scanner
     * was offline, not just repairs existing records.
     */
    public function updateRecord(UpsertAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $userId = auth()->id() !== null ? (int) auth()->id() : null;

        $this->recorder->record($validated, $userId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.attendance.record_saved')]);

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
        $userId = auth()->id() !== null ? (int) auth()->id() : null;

        $count = $this->recorder->bulkPresent($validated, $userId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.attendance.bulk_present', ['count' => $count])]);

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
}
