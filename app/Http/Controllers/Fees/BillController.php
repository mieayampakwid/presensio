<?php

namespace App\Http\Controllers\Fees;

use App\Enums\BillStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\AdjustBillDiscountRequest;
use App\Http\Requests\Fees\BillReasonRequest;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Services\Finance\BillService;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillController extends Controller
{
    /**
     * Display bills with student, class (from enrollments today) and the actions each state allows.
     */
    public function index(Request $request, SchoolSettings $settings): Response
    {
        $today = $settings->todayDate();
        $filters = [
            'search' => $request->string('search')->toString(),
            'class_id' => $request->string('class_id')->toString(),
            'fee_type_id' => $request->string('fee_type_id')->toString(),
            'period_key' => $request->string('period_key')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $bills = Bill::query()
            ->with([
                'student:id,full_name,student_number',
                'student.enrollments' => fn ($query) => $query->activeOn($today)->with('schoolClass:id,name'),
                'feeType:id,name,code',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters) {
                $query->whereHas('student', function (Builder $query) use ($filters) {
                    $query->whereLike('full_name', "%{$filters['search']}%")
                        ->orWhereLike('student_number', "%{$filters['search']}%");
                });
            })
            ->when($filters['class_id'] !== '', function (Builder $query) use ($filters, $today) {
                $query->whereHas('student.enrollments', fn (Builder $query) => $query->activeOn($today)->where('class_id', $filters['class_id']));
            })
            ->when($filters['fee_type_id'] !== '', fn (Builder $query) => $query->where('fee_type_id', $filters['fee_type_id']))
            ->when($filters['period_key'] !== '', fn (Builder $query) => $query->where('period_key', $filters['period_key']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->latest('due_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Bill $bill) => [
                'id' => $bill->id,
                'student_id' => $bill->student_id,
                'student_name' => $bill->student->full_name,
                'student_number' => $bill->student->student_number,
                'class_name' => $bill->student->enrollments->first()?->schoolClass?->name,
                'fee_type_name' => $bill->feeType->name,
                'title' => $bill->title,
                'period_key' => $bill->period_key,
                'due_date' => $bill->due_date->toDateString(),
                'amount' => $bill->amount,
                'discount_amount' => $bill->discount_amount,
                'net_amount' => $bill->netAmount(),
                'paid_amount' => $bill->paid_amount,
                'max_discount' => Money::sub($bill->amount, $bill->paid_amount),
                'status' => $bill->status->value,
                'status_label' => $bill->status->label(),
                'can_adjust_discount' => $bill->canAdjustDiscount(),
                'can_cancel' => $bill->canCancel(),
                'can_write_off' => $bill->canWriteOff(),
            ]);

        $year = AcademicYear::active();

        return Inertia::render('fees/bills', [
            'bills' => $bills,
            'filters' => $filters,
            'classes' => $year
                ? SchoolClass::query()->where('academic_year_id', $year->id)->orderBy('grade_level')->orderBy('name')->get(['id', 'name'])
                : [],
            'feeTypes' => FeeType::query()->orderBy('code')->get(['id', 'name', 'code']),
            'statuses' => array_map(
                fn (BillStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                BillStatus::cases()
            ),
            'canManage' => $request->user()->hasAnyRole(UserRole::Admin, UserRole::Finance),
        ]);
    }

    /**
     * Change a bill's discount; a recognized bill gets an adjusting entry.
     */
    public function discount(AdjustBillDiscountRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->adjustDiscount($bill, $request->string('discount_amount')->toString(), $request->string('reason')->toString());

        return $this->done('Diskon tagihan berhasil diubah.');
    }

    /**
     * Cancel a bill that has no verified payments.
     */
    public function cancel(BillReasonRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->cancel($bill, $request->string('reason')->toString());

        return $this->done('Tagihan berhasil dibatalkan.');
    }

    /**
     * Write off the outstanding receivable of a recognized bill.
     */
    public function writeOff(BillReasonRequest $request, Bill $bill, BillService $billService): RedirectResponse
    {
        $billService->writeOff($bill, $request->string('reason')->toString());

        return $this->done('Piutang berhasil dihapusbukukan.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
