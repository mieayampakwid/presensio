<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read model of the pending bank-transfer queue for finance (spec 14 Requirement 7).
 */
class VerificationQueue
{
    // Shows the oldest pending transfers only; upgrade path is pagination once the backlog routinely exceeds this.
    private const LIMIT = 100;

    public function __construct(private readonly SchoolSettings $settings) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pending(): array
    {
        $payments = Payment::query()
            ->with(['submittedBy:id,username', 'allocations.bill.student:id,full_name'])
            ->where('payment_method', PaymentMethod::BankTransfer)
            ->where('status', PaymentStatus::Pending)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $guardianStudents = [];

        foreach (Guardian::query()->whereIn('user_id', $payments->pluck('submitted_by_user_id')->unique())->with('students:id')->get() as $guardian) {
            foreach ($guardian->students as $student) {
                $guardianStudents[$guardian->user_id][] = $student->id;
            }
        }

        $studentIdsByPayment = [];

        foreach ($payments as $payment) {
            $ids = $guardianStudents[$payment->submitted_by_user_id] ?? [];

            foreach ($payment->allocations as $allocation) {
                $ids[] = $allocation->bill->student_id;
            }

            $studentIdsByPayment[$payment->id] = array_values(array_unique($ids));
        }

        $allStudentIds = array_values(array_unique(array_merge(...array_values($studentIdsByPayment))));

        $openBills = Bill::query()
            ->with('student:id,full_name')
            ->whereIn('student_id', $allStudentIds)
            ->whereIn('status', [BillStatus::Unpaid, BillStatus::PartiallyPaid])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->groupBy('student_id');

        $classes = Enrollment::query()
            ->whereIn('student_id', $allStudentIds)
            ->activeOn($this->settings->todayDate())
            ->with('schoolClass:id,name')
            ->get()
            ->mapWithKeys(fn (Enrollment $enrollment) => [$enrollment->student_id => $enrollment->schoolClass->name]);

        $duplicates = $this->duplicates($payments);

        $rows = [];

        foreach ($payments as $payment) {
            $candidates = [];

            foreach ($studentIdsByPayment[$payment->id] as $studentId) {
                foreach ($openBills->get($studentId, []) as $bill) {
                    $candidates[] = $this->billRow($bill);
                }
            }

            $rows[] = [
                'id' => $payment->id,
                'amount' => Money::toDecimal($payment->amount),
                'payment_date' => $payment->payment_date->toDateString(),
                'origin_bank' => $payment->origin_bank,
                'submitted_by' => $payment->submittedBy->username,
                'notes' => $payment->notes,
                'deposit_account_id' => $payment->deposit_account_id,
                'has_proof' => $payment->proof_path !== null,
                'students' => $payment->allocations->map(fn (PaymentAllocation $allocation) => $allocation->bill->student)
                    ->unique('id')
                    ->map(fn ($student) => ['id' => $student->id, 'full_name' => $student->full_name, 'class_name' => $classes->get($student->id)])
                    ->values()
                    ->all(),
                'allocations' => $payment->allocations->sortBy('bill_id')->map(fn (PaymentAllocation $allocation) => [
                    ...$this->billRow($allocation->bill),
                    'allocation_amount' => Money::toDecimal($allocation->amount),
                ])->values()->all(),
                'duplicates' => $duplicates[$payment->id],
                'candidate_bills' => $candidates,
            ];
        }

        return $rows;
    }

    /**
     * Postable active cash accounts a verified transfer can be deposited to.
     *
     * @return Collection<int, Account>
     */
    public function cashAccounts(): Collection
    {
        return Account::query()
            ->where('is_cash', true)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
    }

    /**
     * Same rule as PaymentService::duplicateProofWarnings, batched over the queue.
     *
     * @param  Collection<int, Payment>  $payments
     * @return array<int, list<array<string, mixed>>>
     */
    private function duplicates(Collection $payments): array
    {
        $matches = Payment::query()
            ->whereIn('proof_hash', $payments->pluck('proof_hash')->filter()->unique()->values())
            ->where('status', '!=', PaymentStatus::Rejected)
            ->orderBy('id')
            ->get();
        $result = [];

        foreach ($payments as $payment) {
            $result[$payment->id] = [];

            foreach ($matches as $other) {
                if ($payment->proof_hash === null || $other->proof_hash !== $payment->proof_hash || $other->id === $payment->id) {
                    continue;
                }

                $result[$payment->id][] = [
                    'id' => $other->id,
                    'receipt_number' => $other->receipt_number,
                    'status_label' => $other->status->label(),
                    'amount' => Money::toDecimal($other->amount),
                    'payment_date' => $other->payment_date->toDateString(),
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function billRow(Bill $bill): array
    {
        return [
            'bill_id' => $bill->id,
            'student_name' => $bill->student->full_name,
            'title' => $bill->title,
            'period_key' => $bill->period_key,
            'due_date' => $bill->due_date->toDateString(),
            'net_amount' => $bill->netAmount(),
            'paid_amount' => Money::toDecimal($bill->paid_amount),
            'remaining' => $bill->remainingAmount(),
        ];
    }
}
