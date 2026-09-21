<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\ExcuseStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use App\Services\Reports\AttendanceReportService;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role landing page (spec 08): a read-only summary of today per role.
 * Numbers, statuses, and deep links only — every widget's drill-down
 * lives on its own page, and loading this page never writes anything.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly SchoolSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $staff = $user->role === UserRole::Admin || $user->role === UserRole::Teacher;

        return Inertia::render('dashboard', [
            'is_school_day' => $this->settings->isSchoolDay($this->settings->todayDate()),
            'board' => $staff ? $this->boardSummary($user) : null,
            'pending_excuses' => $staff ? $this->pendingCount($user) : null,
            'children' => $user->role === UserRole::Parent ? $this->children($user) : null,
            'student' => $user->role === UserRole::Student ? $this->studentToday($user) : null,
        ]);
    }

    /**
     * Today's counts from the same source as the Presence Board, stripped
     * to numbers — the board owns the name-level drill-down (spec 08).
     *
     * @return array{totals: array{enrolled: int, in_building: int, checked_out: int, not_checked_in: int}|null, classes: array<int, array{id: int, name: string, enrolled: int, in_building: int, checked_out: int, not_checked_in: int}>}
     */
    private function boardSummary(User $user): array
    {
        $board = $this->reports->board(
            ClassAccess::classIds($user, AcademicYear::active()?->id),
            $user,
        );

        return [
            'totals' => $board['totals'],
            'classes' => array_map(fn (array $class): array => [
                'id' => $class['id'],
                'name' => $class['name'],
                'enrolled' => $class['enrolled'],
                'in_building' => $class['in_building'],
                'checked_out' => $class['checked_out'],
                'not_checked_in' => $class['not_checked_in'],
            ], $board['classes']),
        ];
    }

    /**
     * Same scoping as the review queue (spec 04): admins count everywhere,
     * teachers count students currently in their homeroom classes.
     */
    private function pendingCount(User $user): int
    {
        return Excuse::query()
            ->where('status', ExcuseStatus::Pending)
            ->when($user->role !== UserRole::Admin, fn ($query) => $query->whereHas(
                'student.enrollments',
                fn ($query) => $query->whereNull('ended_on')->whereIn('class_id', ClassAccess::classIds($user, AcademicYear::active()?->id)),
            ))
            ->count();
    }

    /**
     * Per linked child: today's status (null = no record yet — never
     * "absent") and the child's latest excuse.
     *
     * @return array<int, array{id: int, full_name: string, class_name: string|null, today_status: string|null, latest_excuse: array{type: string, status: string, start_date: string, end_date: string}|null}>|null
     */
    private function children(User $user): ?array
    {
        $guardian = $user->guardian;

        if ($guardian === null) {
            return null;
        }

        $children = $guardian->students()
            ->with('currentEnrollment.schoolClass:id,name')
            ->orderBy('full_name')
            ->get();

        $records = Attendance::query()
            ->whereIn('student_id', $children->modelKeys())
            ->whereDate('date', $this->settings->todayDate())
            ->get(['student_id', 'status'])
            ->keyBy('student_id');

        $latest = Excuse::query()
            ->whereIn('student_id', $children->modelKeys())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['student_id', 'type', 'status', 'start_date', 'end_date'])
            ->groupBy('student_id');

        return $children
            ->map(function (Student $child) use ($records, $latest): array {
                $excuse = $latest->get($child->id)?->first();

                return [
                    'id' => $child->id,
                    'full_name' => $child->full_name,
                    'class_name' => $child->currentEnrollment?->schoolClass?->name,
                    'today_status' => $records->get($child->id)?->status->value,
                    'latest_excuse' => $excuse === null ? null : [
                        'type' => $excuse->type->value,
                        'status' => $excuse->status->value,
                        'start_date' => $excuse->start_date->toDateString(),
                        'end_date' => $excuse->end_date->toDateString(),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return array{today: array{status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null}|null}|null
     */
    private function studentToday(User $user): ?array
    {
        $student = $user->student;

        if ($student === null) {
            return null;
        }

        $record = $student->attendances()
            ->whereDate('date', $this->settings->todayDate())
            ->first(['status', 'checked_in_at', 'checked_out_at', 'scan_method']);

        return ['today' => $this->todayRow($record)];
    }

    /**
     * Mirrors the my-attendance today row: status, pre-formatted school-tz
     * times, and method — no notes, no override attribution.
     *
     * @return array{status: string, checked_in_at: string|null, checked_out_at: string|null, scan_method: string|null}|null
     */
    private function todayRow(?Attendance $record): ?array
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
        ];
    }
}
