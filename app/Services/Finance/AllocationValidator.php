<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Allocation rules of spec 14 §Fee operations (AC-14-05).
 */
class AllocationValidator
{
    public function __construct(private readonly BillStatusCalculator $calculator) {}

    /**
     * Lock the allocated bills (ordered by id) and validate the split against them.
     * Must run inside the caller's transaction, after the payment row is locked.
     *
     * @param  array<int, string>  $allocations  bill id => amount
     * @param  list<int>  $allowedStudentIds
     * @return Collection<int, Bill> locked bills keyed by id, with feeType
     *
     * @throws PaymentOperationException
     */
    public function validate(string $paymentAmount, array $allocations, array $allowedStudentIds): Collection
    {
        if ($allocations === []) {
            throw new PaymentOperationException('Pembayaran harus dialokasikan ke minimal satu tagihan.');
        }

        $bills = Bill::query()
            ->with('feeType')
            ->whereIn('id', array_keys($allocations))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $total = '0.00';

        foreach ($allocations as $billId => $amount) {
            $bill = $bills->get($billId) ?? throw new PaymentOperationException("Tagihan #{$billId} tidak ditemukan.");
            $amount = Money::toDecimal($amount);

            if (! in_array($bill->student_id, $allowedStudentIds, true)) {
                throw new PaymentOperationException("Tagihan \"{$bill->title}\" bukan milik siswa yang dipilih.");
            }

            $paid = $this->calculator->paidAmount($bill);

            if (! in_array($this->calculator->statusFor($bill, $paid), [BillStatus::Unpaid, BillStatus::PartiallyPaid], true)) {
                throw new PaymentOperationException("Tagihan \"{$bill->title}\" tidak dapat dibayar.");
            }

            if (! Money::isPositive($amount)) {
                throw new PaymentOperationException('Setiap alokasi harus lebih besar dari 0.');
            }

            if (Money::gt($amount, Money::sub($bill->netAmount(), $paid))) {
                throw new PaymentOperationException("Alokasi untuk tagihan \"{$bill->title}\" melebihi sisa tagihan.");
            }

            $total = Money::add($total, $amount);
        }

        if (Money::cmp($total, $paymentAmount) !== 0) {
            throw new PaymentOperationException('Total alokasi harus sama dengan jumlah pembayaran.');
        }

        return $bills;
    }
}
