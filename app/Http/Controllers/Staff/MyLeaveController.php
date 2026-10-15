<?php

namespace App\Http\Controllers\Staff;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreLeaveRequest;
use App\Models\Employee;
use App\Models\EmployeeLeaveRequest;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee self-service for leave requests (spec 16 §Req 4): list own
 * submissions, submit a new range, cancel while still pending. Available
 * to any authenticated user with an employee profile — teachers and TU
 * staff alike; accounts without an employee profile get 403.
 */
class MyLeaveController extends Controller
{
    private const DISK = 'local';

    private const DIRECTORY = 'leave-attachments';

    public function __construct(
        private readonly SchoolSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $employee = $this->employee($request);

        /** @var LengthAwarePaginator<int, EmployeeLeaveRequest> $leaveRequests */
        $leaveRequests = EmployeeLeaveRequest::query()
            ->forEmployee($employee->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('staff/my-leave', [
            'requests' => $leaveRequests->through(
                fn (EmployeeLeaveRequest $leaveRequest): array => $this->row($leaveRequest),
            ),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $employee = $this->employee($request);

        $validated = $request->validated();
        $validated['employee_id'] = $employee->id;
        $validated['attachment_path'] = $this->storeAttachment($request);
        $validated['status'] = LeaveRequestStatus::Pending;

        EmployeeLeaveRequest::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Leave request submitted — awaiting review.']);

        return to_route('my-leave.index');
    }

    public function destroy(Request $request, EmployeeLeaveRequest $leave_request): RedirectResponse
    {
        $employee = $this->employee($request);

        abort_unless($leave_request->employee_id === $employee->id, 403);

        if ($leave_request->status !== LeaveRequestStatus::Pending) {
            abort(422, 'Only pending leave requests can be cancelled.');
        }

        $leave_request->update(['status' => LeaveRequestStatus::Cancelled]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Leave request cancelled.']);

        return to_route('my-leave.index');
    }

    /**
     * Attachment download for the owning employee only (reviewers get
     * their own route with the review queue).
     */
    public function attachment(Request $request, EmployeeLeaveRequest $leave_request): StreamedResponse
    {
        $employee = $this->employee($request);

        abort_unless($leave_request->employee_id === $employee->id, 403);

        $path = $leave_request->attachment_path;

        abort_if($path === null, 404);

        $extension = pathinfo((string) $path, PATHINFO_EXTENSION);

        return Storage::disk(self::DISK)->download((string) $path, "leave-{$leave_request->id}.{$extension}");
    }

    /**
     * The acting user's employee profile; any authenticated user without
     * one is forbidden (spec 16 §Req 4).
     */
    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_unless($employee instanceof Employee, 403);

        return $employee;
    }

    /**
     * Proof upload on the private local disk under an opaque token name —
     * served back only through the authorized attachment route.
     */
    private function storeAttachment(StoreLeaveRequest $request): ?string
    {
        $file = $request->file('attachment');

        if ($file === null) {
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        $token = bin2hex(random_bytes(20));
        $stored = $file->storeAs(self::DIRECTORY, $token.'.'.$extension, self::DISK);

        if ($stored === false) {
            throw new RuntimeException('Could not persist the leave request attachment.');
        }

        return $stored;
    }

    /**
     * @return array{id: int, type: string, start_date: string, end_date: string, reason: string, has_attachment: bool, attachment_url: string|null, status: string, review_note: string|null, submitted_at: string}
     */
    private function row(EmployeeLeaveRequest $leaveRequest): array
    {
        return [
            'id' => $leaveRequest->id,
            'type' => $leaveRequest->type->value,
            'start_date' => $leaveRequest->start_date->toDateString(),
            'end_date' => $leaveRequest->end_date->toDateString(),
            'reason' => $leaveRequest->reason,
            'has_attachment' => $leaveRequest->attachment_path !== null,
            'attachment_url' => $leaveRequest->attachment_path !== null
                ? route('my-leave.attachment', ['leave_request' => $leaveRequest->id])
                : null,
            'status' => $leaveRequest->status->value,
            'review_note' => $leaveRequest->review_note,
            'submitted_at' => $leaveRequest->created_at?->tz($this->settings->timezone())->format('Y-m-d H:i'),
        ];
    }
}
