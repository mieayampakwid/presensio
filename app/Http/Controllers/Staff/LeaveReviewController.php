<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\ApproveLeaveRequest;
use App\Http\Requests\Staff\RejectLeaveRequest;
use App\Models\EmployeeLeaveRequest;
use App\Services\Attendance\LeaveApprovalService;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leave review queue (spec 16 §Req 5): admins and principals review
 * employee-submitted leave requests. Approval writes attendance for the
 * expected days (LeaveApprovalService); rejection stamps the decision
 * only. Every row carries its attachment so the reviewer can check the
 * proof before deciding.
 */
class LeaveReviewController extends Controller
{
    private const DISK = 'local';

    public function __construct(
        private readonly LeaveApprovalService $approver,
        private readonly SchoolSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        // Default to the actionable queue; ?status= switches buckets.
        $status = LeaveRequestStatus::tryFrom($request->string('status')->toString())
            ?? LeaveRequestStatus::Pending;
        $search = trim($request->string('search')->toString());

        /** @var LengthAwarePaginator<int, EmployeeLeaveRequest> $leaveRequests */
        $leaveRequests = EmployeeLeaveRequest::query()
            ->with(['employee:id,name', 'reviewedBy:id,username'])
            ->where('status', $status)
            ->when($search !== '', fn ($query) => $query->whereHas(
                'employee',
                fn ($query) => $query->where('name', 'like', "%{$search}%"),
            ))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('staff/leave-review', [
            'requests' => $leaveRequests->through(
                fn (EmployeeLeaveRequest $leaveRequest): array => $this->row($leaveRequest),
            ),
            'filters' => ['status' => $status->value, 'search' => $search],
        ]);
    }

    public function approve(ApproveLeaveRequest $formRequest, EmployeeLeaveRequest $leave_request): RedirectResponse
    {
        return $this->resolve(
            fn (): bool => $this->approver->approve($leave_request, $formRequest->user(), $formRequest->validated('review_note')),
            'Leave request approved — attendance updated.',
        );
    }

    public function reject(RejectLeaveRequest $formRequest, EmployeeLeaveRequest $leave_request): RedirectResponse
    {
        return $this->resolve(
            fn (): bool => $this->approver->reject($leave_request, $formRequest->user(), (string) $formRequest->validated('review_note')),
            'Leave request rejected.',
        );
    }

    /**
     * Proof download for reviewers; the owner has their own route on the
     * self-service controller.
     */
    public function attachment(Request $request, EmployeeLeaveRequest $leave_request): StreamedResponse
    {
        $path = $leave_request->attachment_path;

        abort_if($path === null, 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->download((string) $path, "leave-{$leave_request->id}.{$extension}");
    }

    /**
     * @param  callable(): bool  $action
     */
    private function resolve(callable $action, string $successMessage): RedirectResponse
    {
        $applied = $action();

        // Terminal states: acting on an already-resolved request is a
        // no-op info flash, not an error (same decision as excuses).
        Inertia::flash('toast', $applied
            ? ['type' => 'success', 'message' => $successMessage]
            : ['type' => 'info', 'message' => 'Leave request was already resolved.']);

        return back();
    }

    /**
     * @return array{id: int, employee_name: string, type: string, start_date: string, end_date: string, reason: string, has_attachment: bool, attachment_url: string|null, status: string, review_note: string|null, reviewer_name: string|null, submitted_at: string|null}
     */
    private function row(EmployeeLeaveRequest $leaveRequest): array
    {
        return [
            'id' => $leaveRequest->id,
            'employee_name' => $leaveRequest->employee->name,
            'type' => $leaveRequest->type->value,
            'start_date' => $leaveRequest->start_date->toDateString(),
            'end_date' => $leaveRequest->end_date->toDateString(),
            'reason' => $leaveRequest->reason,
            'has_attachment' => $leaveRequest->attachment_path !== null,
            'attachment_url' => $leaveRequest->attachment_path !== null
                ? route('staff-leave.attachment', ['leave_request' => $leaveRequest->id])
                : null,
            'status' => $leaveRequest->status->value,
            'review_note' => $leaveRequest->review_note,
            'reviewer_name' => $leaveRequest->reviewedBy?->username,
            'submitted_at' => $leaveRequest->created_at?->tz($this->settings->timezone())->format('Y-m-d H:i'),
        ];
    }
}
