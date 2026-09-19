<?php

namespace App\Http\Controllers\Excuses;

use App\Enums\ExcuseStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Excuses\ReviewExcuseRequest;
use App\Models\Attendance;
use App\Models\Excuse;
use App\Services\Attendance\ClassAccess;
use App\Services\Attendance\ExcuseApprovalService;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Excuse review queue (spec 04 §Requirements 3): admins review everywhere,
 * teachers see their homeroom classes read-only. Pending rows float to the
 * top; every row carries the attendance state of its range's school days
 * so the reviewer can see what approval would overwrite.
 */
class ExcuseReviewController extends Controller
{
    /**
     * Per-request memo for the school-day check — one NonSchoolDay lookup
     * per distinct date on the page instead of per excuse per day.
     *
     * @var array<string, bool>
     */
    private array $schoolDayCache = [];

    public function __construct(
        private readonly ExcuseApprovalService $approver,
        private readonly SchoolSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        /** @var LengthAwarePaginator<int, Excuse> $excuses */
        $excuses = Excuse::query()
            ->with('student.schoolClass:id,name')
            // Admins review everywhere (even a student temporarily without
            // a class); teachers see their homeroom classes only.
            ->when($user->role !== UserRole::Admin, fn ($query) => $query->whereHas(
                'student',
                fn ($query) => $query->whereIn('class_id', ClassAccess::classIds($user)),
            ))
            ->orderByRaw('case when status = ? then 0 else 1 end', [ExcuseStatus::Pending->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $attendance = $this->attendanceInRange($excuses->getCollection());

        return Inertia::render('excuses/index', [
            'excuses' => $excuses->through(fn (Excuse $excuse) => $this->row($excuse, $attendance)),
            'can_review' => $user->role === UserRole::Admin,
        ]);
    }

    public function approve(ReviewExcuseRequest $request, Excuse $excuse): RedirectResponse
    {
        return $this->resolve(
            fn (): bool => $this->approver->approve($excuse, $request->user(), $request->validated('review_note')),
            'Excuse approved — attendance updated.',
        );
    }

    public function reject(ReviewExcuseRequest $request, Excuse $excuse): RedirectResponse
    {
        return $this->resolve(
            fn (): bool => $this->approver->reject($excuse, $request->user(), $request->validated('review_note')),
            'Excuse rejected.',
        );
    }

    /**
     * @param  callable(): bool  $action
     */
    private function resolve(callable $action, string $successMessage): RedirectResponse
    {
        $applied = $action();

        // Terminal states: acting on an already-resolved excuse is a
        // no-op info flash, not an error (spec 04 decision).
        Inertia::flash('toast', $applied
            ? ['type' => 'success', 'message' => $successMessage]
            : ['type' => 'info', 'message' => 'Excuse was already resolved.']);

        return back();
    }

    /**
     * Attendance rows for every excuse on the page — one query for the
     * whole page, keyed student|date.
     *
     * @param  Collection<int, Excuse>  $excuses
     * @return array<string, Attendance>
     */
    private function attendanceInRange(Collection $excuses): array
    {
        if ($excuses->isEmpty()) {
            return [];
        }

        return Attendance::query()
            ->whereIn('student_id', $excuses->pluck('student_id')->unique())
            ->whereBetween('date', [
                $excuses->min(fn (Excuse $excuse) => $excuse->start_date->toDateString()),
                $excuses->max(fn (Excuse $excuse) => $excuse->end_date->toDateString()),
            ])
            ->get()
            ->keyBy(fn (Attendance $record) => $record->student_id.'|'.$record->date->format('Y-m-d'))
            ->all();
    }

    /**
     * @param  array<string, Attendance>  $attendance
     * @return array{id: int, student_name: string, class_name: string|null, type: string, start_date: string, end_date: string, reason: string, has_attachment: bool, attachment_url: string|null, status: string, review_note: string|null, submitted_at: string, days: array<int, array{date: string, status: string|null}>}
     */
    private function row(Excuse $excuse, array $attendance): array
    {
        return [
            'id' => $excuse->id,
            'student_name' => $excuse->student->full_name,
            'class_name' => $excuse->student->schoolClass?->name,
            'type' => $excuse->type->value,
            'start_date' => $excuse->start_date->toDateString(),
            'end_date' => $excuse->end_date->toDateString(),
            'reason' => $excuse->reason,
            'has_attachment' => $excuse->attachment_path !== null,
            'attachment_url' => $excuse->attachment_path !== null
                ? route('excuses.attachment', ['excuse' => $excuse->id])
                : null,
            'status' => $excuse->status->value,
            'review_note' => $excuse->review_note,
            'submitted_at' => $excuse->created_at?->tz($this->settings->timezone())->format('Y-m-d H:i'),
            'days' => $this->rangeDays($excuse, $attendance),
        ];
    }

    /**
     * Attendance state per school day in the excuse's range.
     *
     * @param  array<string, Attendance>  $attendance
     * @return array<int, array{date: string, status: string|null}>
     */
    private function rangeDays(Excuse $excuse, array $attendance): array
    {
        $days = [];

        $end = Date::parse($excuse->end_date->toDateString());

        for ($day = Date::parse($excuse->start_date->toDateString());
            $day->lessThanOrEqualTo($end);
            $day = $day->addDay()) {
            $date = $day->toDateString();

            if (! ($this->schoolDayCache[$date] ??= $this->settings->isSchoolDay($date))) {
                continue;
            }

            $record = $attendance[$excuse->student_id.'|'.$date] ?? null;

            $days[] = ['date' => $date, 'status' => $record?->status->value];
        }

        return $days;
    }
}
