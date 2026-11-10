<?php

namespace App\Http\Controllers\Students;

use App\Enums\BillStatus;
use App\Enums\FeeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Students\WithdrawStudentRequest;
use App\Models\Bill;
use App\Models\Student;
use App\Services\Audit\AuditLogger;
use App\Services\EnrollmentService;
use App\Services\Finance\BillStatusCalculator;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentWithdrawalController extends Controller
{
    public function __construct(
        private readonly EnrollmentService $enrollmentService,
        private readonly SchoolSettings $schoolSettings,
        private readonly BillStatusCalculator $calculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Preview the impact of withdrawing the student as of a given date (defaults to today).
     */
    public function preview(Request $request, Student $student): JsonResponse
    {
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->with('schoolClass')->first();

        if ($activeEnrollment === null) {
            return response()->json([
                'has_active_enrollment' => false,
            ]);
        }

        $endDateStr = $request->string('ended_on')->toString();
        $endDate = $endDateStr !== '' ? Date::parse($endDateStr) : Date::parse($this->schoolSettings->todayDate());

        $bills = Bill::query()
            ->with('feeType')
            ->where('student_id', $student->id)
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->orderBy('due_date')
            ->get();

        $cancelledBills = [];
        $reviewBills = [];

        foreach ($bills as $bill) {
            $start = $bill->feeType->type === FeeKind::OneTime
                ? Date::parse($bill->due_date->toDateString())
                : Date::parse($bill->period_key.'-01');

            if (! $start->greaterThan($endDate)) {
                continue;
            }

            if (Money::isPositive($this->calculator->paidAmount($bill))) {
                $reviewBills[] = [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'period_key' => $bill->period_key,
                    'net_amount' => $bill->netAmount(),
                    'paid_amount' => $this->calculator->paidAmount($bill),
                ];
            } else {
                $cancelledBills[] = [
                    'id' => $bill->id,
                    'title' => $bill->title,
                    'period_key' => $bill->period_key,
                    'net_amount' => $bill->netAmount(),
                ];
            }
        }

        return response()->json([
            'has_active_enrollment' => true,
            'class_id' => $activeEnrollment->class_id,
            'class_name' => $activeEnrollment->schoolClass?->name,
            'started_on' => $activeEnrollment->started_on->toDateString(),
            'has_rfid' => $student->rfidCard !== null,
            'has_user_account' => $student->user_id !== null,
            'cancelled_bills_count' => count($cancelledBills),
            'review_bills_count' => count($reviewBills),
            'review_bills' => $reviewBills,
        ]);
    }

    /**
     * Execute the withdrawal of the student.
     */
    public function store(WithdrawStudentRequest $request, Student $student): RedirectResponse
    {
        $activeEnrollment = $student->enrollments()->whereNull('ended_on')->first();

        if ($activeEnrollment === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('toasts.students.withdraw_no_active_enrollment')]);

            return to_route('students.index');
        }

        DB::transaction(function () use ($request, $student, $activeEnrollment): void {
            $review = $this->enrollmentService->release(
                $student,
                $request->endedOn(),
                $request->reason(),
                $request->notes(),
            );

            if ($request->shouldReleaseRfid() && $student->rfidCard !== null) {
                $student->rfidCard->update(['student_id' => null]);
            }

            if ($request->shouldDeactivateUser() && $student->user !== null) {
                $student->user->update(['is_active' => false]);
            }

            $this->auditLogger->record(
                $student,
                'student.withdrawn',
                [
                    'class_id' => $activeEnrollment->class_id,
                    'started_on' => $activeEnrollment->started_on->toDateString(),
                ],
                [
                    'ended_on' => $request->endedOn(),
                    'reason' => $request->reason()->value,
                    'notes' => $request->notes(),
                    'review_bills_count' => count($review),
                    'rfid_released' => $request->shouldReleaseRfid(),
                    'user_deactivated' => $request->shouldDeactivateUser(),
                ],
                $request->notes() ?? $request->reason()->label(),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('toasts.students.withdrawn', ['name' => $student->full_name]),
        ]);

        return to_route('students.index');
    }
}
