<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\PaymentAllocation;
use App\Support\Money;

/**
 * Derives a bill's status from its net amount and verified allocations (spec 14 §Fee operations).
 */
class BillStatusCalculator
{
    /**
     * Sum of allocations on verified payments only; pending, rejected and void never count.
     */
    public function paidAmount(Bill $bill): string
    {
        return Money::toDecimal(
            PaymentAllocation::query()
                ->where('bill_id', $bill->id)
                ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Verified))
                ->sum('amount')
        );
    }

    public function calculate(Bill $bill): BillStatus
    {
        return $this->statusFor($bill, $this->paidAmount($bill));
    }

    /**
     * Cancelled / written-off stamps win, then waived, paid, partially paid, unpaid.
     */
    public function statusFor(Bill $bill, string $paid): BillStatus
    {
        $net = $bill->netAmount();

        return match (true) {
            $bill->cancelled_at !== null => BillStatus::Cancelled,
            $bill->written_off_at !== null => BillStatus::WrittenOff,
            Money::isZero($net) => BillStatus::Waived,
            Money::gte($paid, $net) => BillStatus::Paid,
            Money::isPositive($paid) => BillStatus::PartiallyPaid,
            default => BillStatus::Unpaid,
        };
    }
}
