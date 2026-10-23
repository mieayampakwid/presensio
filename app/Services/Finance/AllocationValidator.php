<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\PaymentAllocation;
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
     * Must run inside the caller's transaction, after any existing payment row is locked.
     *
     * @param  array<int, string>  $allocations  bill id => amount
     * @param  list<int>  $allowedStudentIds
     * @return Collection<int, Bill> locked bills keyed by id, with feeType
     *
     * @throws PaymentOperationException
     */
    public function validate(
        string $paymentAmount,
        array $allocations,
        array $allowedStudentIds,
        bool $respectPendingClaims = false,
        ?int $excludePaymentId = null,
    ): Collection {
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

        $pendingByBill = $respectPendingClaims
            ? PaymentAllocation::query()
                ->whereIn('bill_id', $bills->keys())
                ->when($excludePaymentId !== null, fn ($query) => $query->where('payment_id', '!=', $excludePaymentId))
                ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Pending))
                ->get(['bill_id', 'amount'])
                ->groupBy('bill_id')
                ->map(fn (Collection $rows) => $rows->reduce(
                    fn (string $sum, PaymentAllocation $row) => Money::add($sum, $row->amount),
                    '0.00',
                ))
            : collect();

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

            $remaining = Money::sub($bill->netAmount(), $paid);
            $pending = $pendingByBill->get($billId, '0.00');
            $claimable = Money::sub($remaining, $pending);

            if ($respectPendingClaims && Money::gt($amount, $claimable) && Money::isPositive($pending)) {
                $display = Money::isNegative($claimable) ? '0.00' : $claimable;

                throw new PaymentOperationException(
                    'Alokasi untuk tagihan "'.$bill->title.'" melebihi jumlah yang dapat diklaim ('.Money::format($display).'). '
                    .'Ada transfer menunggu verifikasi untuk tagihan ini. Periksa daftar transfer yang dapat Anda batalkan atau hubungi bendahara.'
                );
            }

            if (Money::gt($amount, $remaining)) {
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
