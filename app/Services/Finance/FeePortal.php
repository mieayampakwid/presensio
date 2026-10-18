<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Read model of a set of students' fees for the guardian portal (spec 14 §Fee operations, item 8).
 */
class FeePortal
{
    private const PAYMENT_LIMIT = 50;

    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return list<array{id: int, full_name: string, class_name: string|null}>
     */
    public function children(Collection|array $studentIds): array
    {
        $ids = collect($studentIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $classes = $this->classNames($ids);

        return array_values(Student::query()->whereIn('id', $ids)->orderBy('full_name')->get(['id', 'full_name'])
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'class_name' => $classes[$student->id] ?? null,
            ])->all());
    }

    /**
     * @param  Collection<int, int>|array<int, int>  $studentIds
     * @return array{summary: array{unpaid: string, pending: string, paid: string}, bills: list<array<string, mixed>>, payments: list<array<string, mixed>>}
     */
    public function forStudents(Collection|array $studentIds): array
    {
        $ids = collect($studentIds)->map(fn ($id) => (int) $id)->unique()->values()->all();

        $bills = Bill::query()
            ->with(['feeType:id,name', 'student:id,full_name'])
            ->whereIn('student_id', $ids)
            ->where('status', '!=', BillStatus::Cancelled)
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $pendingByBill = PaymentAllocation::query()
            ->whereIn('bill_id', $bills->modelKeys())
            ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Pending))
            ->get(['bill_id', 'amount'])
            ->groupBy('bill_id')
            ->map(fn (Collection $rows) => $rows->reduce(fn (string $sum, PaymentAllocation $row) => Money::add($sum, $row->amount), '0.00'));

        $classes = $this->classNames($ids);
        $openStatuses = [BillStatus::Unpaid, BillStatus::PartiallyPaid];

        return [
            'summary' => [
                'unpaid' => $bills->filter(fn (Bill $bill) => in_array($bill->status, $openStatuses, true))
                    ->reduce(fn (string $sum, Bill $bill) => Money::add($sum, $bill->remainingAmount()), '0.00'),
                'pending' => $pendingByBill->reduce(fn (string $sum, string $amount) => Money::add($sum, $amount), '0.00'),
                'paid' => $bills->reduce(fn (string $sum, Bill $bill) => Money::add($sum, $bill->paid_amount), '0.00'),
            ],
            'bills' => array_values($bills->map(fn (Bill $bill) => [
                'id' => $bill->id,
                'student_id' => $bill->student_id,
                'student_name' => $bill->student->full_name,
                'class_name' => $classes[$bill->student_id] ?? null,
                'fee_type_name' => $bill->feeType->name,
                'title' => $bill->title,
                'period_key' => $bill->period_key,
                'due_date' => $bill->due_date->toDateString(),
                'net_amount' => $bill->netAmount(),
                'paid_amount' => Money::toDecimal($bill->paid_amount),
                'remaining' => $bill->remainingAmount(),
                'status' => $bill->status->value,
                'status_label' => $bill->status->label(),
                'has_pending' => $pendingByBill->has($bill->id),
            ])->all()),
            'payments' => $this->payments($ids),
        ];
    }

    /**
     * Payments whose students all belong to the set (the receipt access rule).
     *
     * @param  array<int, int>  $ids
     * @return list<array<string, mixed>>
     */
    private function payments(array $ids): array
    {
        return array_values(Payment::query()
            ->whereHas('allocations.bill', fn ($query) => $query->whereIn('student_id', $ids))
            ->whereDoesntHave('allocations.bill', fn ($query) => $query->whereNotIn('student_id', $ids))
            ->latest('id')
            ->limit(self::PAYMENT_LIMIT)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => Money::toDecimal($payment->amount),
                'payment_date' => $payment->payment_date->toDateString(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'origin_bank' => $payment->origin_bank,
                'reject_reason' => $payment->reject_reason,
                'can_view_receipt' => in_array($payment->status, [PaymentStatus::Verified, PaymentStatus::Void], true),
                'created_at' => $payment->created_at?->toIso8601String(),
            ])->all());
    }

    /**
     * Today's class per student, from enrollments.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function classNames(array $ids): array
    {
        return Enrollment::query()
            ->whereIn('student_id', $ids)
            ->activeOn($this->settings->todayDate())
            ->with('schoolClass:id,name')
            ->get()
            ->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->student_id => $enrollment->schoolClass->name])
            ->all();
    }
}
