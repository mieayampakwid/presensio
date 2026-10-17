<?php

namespace App\Services\Finance;

use App\Enums\BillStatus;
use App\Enums\FeeKind;
use App\Enums\JournalSource;
use App\Models\Bill;
use App\Models\JournalEntry;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Exceptions\BillOperationException;
use App\Services\SchoolSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Bill lifecycle against the ledger (spec 14 §Accrual posting rules). Every state
 * change posts through JournalPoster inside the same transaction, so a posting
 * failure fails the operation.
 */
class BillService
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly PeriodService $periodService,
        private readonly BillStatusCalculator $calculator,
        private readonly SchoolSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Natural recognition date: first day of the period month, or the issue date for one-time fees.
     */
    public function recognitionDate(Bill $bill): CarbonInterface
    {
        $bill->loadMissing('feeType');

        if ($bill->feeType->type === FeeKind::OneTime) {
            return Date::parse($bill->created_at->copy()->tz($this->settings->timezone())->toDateString());
        }

        return Date::parse($bill->period_key.'-01');
    }

    /**
     * Post the recognition entry once the recognition date has been reached.
     * Idempotent: a bill that already has a recognition entry is left alone. Returns whether it posted.
     *
     * A prepaid portion (verified allocations made before recognition) is consumed from the prepaid
     * revenue account instead of the receivable (Dr P instead of Dr R, AC-14-15); the payment flow
     * only needs to create those allocations and call recompute().
     */
    public function recognize(Bill $bill): bool
    {
        return DB::transaction(function () use ($bill) {
            $bill = $this->lock($bill);

            if ($bill->isRecognized() || $bill->cancelled_at !== null || $bill->written_off_at !== null) {
                return false;
            }

            $natural = $this->recognitionDate($bill);

            if ($natural->greaterThan($this->today())
                || JournalEntry::query()->where('source_type', JournalSource::BillRecognition)->where('source_id', $bill->id)->exists()) {
                return false;
            }

            [$date, $memo] = $this->postingDate($natural);
            $prepaid = $this->prepaidPortion($bill);

            $entry = $this->poster->post(new PostableEntry(
                entryDate: $date,
                description: "Pengakuan tagihan: {$bill->title}",
                sourceType: JournalSource::BillRecognition,
                lines: $this->lines($bill, [
                    [$bill->feeType->receivable_account_id, 'debit', Money::sub($this->recognizedCredit($bill), Money::add($this->recognizedDiscount($bill), $prepaid))],
                    ...(Money::isPositive($prepaid) ? [[$this->requiredAccount('prepaid_revenue_account_id', 'pendapatan diterima di muka'), 'debit', $prepaid]] : []),
                    [$bill->feeType->discount_account_id, 'debit', $this->recognizedDiscount($bill)],
                    [$this->revenueAccountId($bill), 'credit', $this->recognizedCredit($bill)],
                ], $memo),
                sourceId: $bill->id,
                createdByUserId: $this->userId(),
            ));

            $bill->update(['recognized_at' => $entry->entry_date]);

            return true;
        });
    }

    /**
     * Change the discount on a bill. On a recognized bill the difference is posted as an adjusting entry.
     */
    public function adjustDiscount(Bill $bill, string $discountAmount, ?string $reason): Bill
    {
        return DB::transaction(function () use ($bill, $discountAmount, $reason) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);

            $new = Money::toDecimal($discountAmount);

            if (Money::isNegative($new) || Money::gt($new, $bill->amount)) {
                throw new BillOperationException('Diskon harus antara 0 dan jumlah tagihan.');
            }

            if (Money::lt(Money::sub($bill->amount, $new), $bill->paid_amount)) {
                throw new BillOperationException('Diskon tidak boleh membuat tagihan lebih kecil dari jumlah yang sudah dibayar.');
            }

            $old = $bill->discount_amount;
            $difference = Money::sub($new, $old);

            if ($bill->isRecognized() && ! Money::isZero($difference)) {
                [$date, $memo] = $this->postingDate($this->today());
                $increase = Money::isPositive($difference);
                $magnitude = Money::abs($difference);

                $this->poster->post(new PostableEntry(
                    entryDate: $date,
                    description: "Penyesuaian diskon: {$bill->title}",
                    sourceType: JournalSource::BillAdjustment,
                    lines: $this->lines($bill, [
                        [$this->adjustmentAccountId($bill), $increase ? 'debit' : 'credit', $magnitude],
                        [$bill->feeType->receivable_account_id, $increase ? 'credit' : 'debit', $magnitude],
                    ], $memo),
                    sourceId: $bill->id,
                    createdByUserId: $this->userId(),
                ));
            }

            $bill->update(['discount_amount' => $new, 'discount_reason' => $reason]);
            $this->recompute($bill);

            $this->auditLogger->record(
                $bill,
                'bill_discount_changed',
                ['discount_amount' => $old],
                ['discount_amount' => $new],
                $reason,
            );

            return $bill;
        });
    }

    /**
     * Cancel a bill that has no verified allocations; recognition is reversed for the unpaid portion.
     */
    public function cancel(Bill $bill, string $reason): Bill
    {
        return DB::transaction(function () use ($bill, $reason) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);

            if (Money::isPositive($this->calculator->paidAmount($bill))) {
                throw new BillOperationException('Tagihan yang sudah memiliki pembayaran terverifikasi tidak dapat dibatalkan. Batalkan pembayarannya terlebih dahulu.');
            }

            if ($bill->isRecognized()) {
                [$date, $memo] = $this->postingDate($this->today());
                $recognition = JournalEntry::query()
                    ->where('source_type', JournalSource::BillRecognition)
                    ->where('source_id', $bill->id)
                    ->first();

                $this->poster->post(new PostableEntry(
                    entryDate: $date,
                    description: "Pembatalan tagihan: {$bill->title}",
                    sourceType: JournalSource::BillCancellation,
                    lines: $this->lines($bill, [
                        [$this->revenueAccountId($bill), 'debit', $this->recognizedCredit($bill)],
                        [$bill->feeType->receivable_account_id, 'credit', $bill->netAmount()],
                        [$bill->feeType->discount_account_id, 'credit', $this->recognizedDiscount($bill)],
                    ], $memo),
                    sourceId: $bill->id,
                    reversesEntryId: $recognition?->id,
                    createdByUserId: $this->userId(),
                ));
            }

            $bill->update(['cancelled_at' => now()->tz('UTC'), 'cancel_reason' => $reason]);
            $this->recompute($bill);

            $this->auditLogger->record($bill, 'bill_cancelled', ['status' => BillStatus::Unpaid->value], ['status' => BillStatus::Cancelled->value], $reason);

            return $bill;
        });
    }

    /**
     * Write off the outstanding amount of a recognized bill (AC-14-21): Dr bad-debt expense / Cr receivable.
     */
    public function writeOff(Bill $bill, string $reason): Bill
    {
        return DB::transaction(function () use ($bill, $reason) {
            $bill = $this->lock($bill);
            $this->assertOpen($bill);

            if (! $bill->isRecognized()) {
                throw new BillOperationException('Hanya tagihan yang sudah diakui yang dapat dihapusbukukan.');
            }

            $outstanding = Money::sub($bill->netAmount(), $this->calculator->paidAmount($bill));

            if (! Money::isPositive($outstanding)) {
                throw new BillOperationException('Tagihan sudah lunas, tidak ada piutang yang dapat dihapusbukukan.');
            }

            [$date, $memo] = $this->postingDate($this->today());

            $this->poster->post(new PostableEntry(
                entryDate: $date,
                description: "Penghapusan piutang: {$bill->title}",
                sourceType: JournalSource::WriteOff,
                lines: $this->lines($bill, [
                    [$this->requiredAccount('bad_debt_expense_account_id', 'beban penghapusan piutang'), 'debit', $outstanding],
                    [$bill->feeType->receivable_account_id, 'credit', $outstanding],
                ], $memo),
                sourceId: $bill->id,
                createdByUserId: $this->userId(),
            ));

            $previous = $bill->status->value;
            $bill->update(['written_off_at' => now()->tz('UTC'), 'write_off_reason' => $reason]);
            $this->recompute($bill);

            $this->auditLogger->record($bill, 'bill_written_off', ['status' => $previous], ['status' => BillStatus::WrittenOff->value, 'amount' => $outstanding], $reason);

            return $bill;
        });
    }

    /**
     * Refresh the cached paid_amount and status from verified allocations.
     */
    public function recompute(Bill $bill): Bill
    {
        $paid = $this->calculator->paidAmount($bill);

        $bill->update([
            'paid_amount' => $paid,
            'status' => $this->calculator->statusFor($bill, $paid),
        ]);

        return $bill;
    }

    /**
     * Verified allocations on a not-yet-recognized bill, capped at net.
     */
    private function prepaidPortion(Bill $bill): string
    {
        $paid = $this->calculator->paidAmount($bill);

        return Money::lt($paid, $bill->netAmount()) ? $paid : $bill->netAmount();
    }

    private function lock(Bill $bill): Bill
    {
        return Bill::query()->with('feeType')->lockForUpdate()->findOrFail($bill->id);
    }

    private function assertOpen(Bill $bill): void
    {
        if ($bill->cancelled_at !== null || $bill->written_off_at !== null) {
            throw new BillOperationException('Tagihan sudah dibatalkan atau dihapusbukukan.');
        }
    }

    private function today(): CarbonInterface
    {
        return Date::parse($this->settings->todayDate());
    }

    private function userId(): ?int
    {
        return auth()->id() !== null ? (int) auth()->id() : null;
    }

    /**
     * An open-period entry date; a closed natural period moves to the next open period's first day.
     *
     * @return array{CarbonInterface, string|null}
     */
    private function postingDate(CarbonInterface $natural): array
    {
        $date = $this->periodService->nextOpenPeriodStart($natural);

        return [$date, $date->toDateString() === $natural->toDateString() ? null : "Tanggal asli: {$natural->toDateString()}"];
    }

    /**
     * Opening bills credit net assets (net of discount) instead of revenue and carry no discount line.
     */
    private function recognizedCredit(Bill $bill): string
    {
        return $bill->is_opening ? $bill->netAmount() : $bill->amount;
    }

    private function recognizedDiscount(Bill $bill): string
    {
        return $bill->is_opening ? '0.00' : $bill->discount_amount;
    }

    private function revenueAccountId(Bill $bill): int
    {
        return $bill->is_opening
            ? $this->requiredAccount('net_assets_closing_account_id', 'penutup aset neto')
            : $bill->feeType->revenue_account_id;
    }

    /**
     * Counter-account for discount adjustments: contra revenue, or net assets for opening bills.
     */
    private function adjustmentAccountId(Bill $bill): int
    {
        return $bill->is_opening
            ? $this->requiredAccount('net_assets_closing_account_id', 'penutup aset neto')
            : $bill->feeType->discount_account_id;
    }

    private function requiredAccount(string $column, string $label): int
    {
        $id = $this->settings->row()->{$column};

        if ($id === null) {
            throw new BillOperationException("Akun {$label} belum diatur di pengaturan sekolah.");
        }

        return (int) $id;
    }

    /**
     * Build subledger-keyed lines, dropping zero amounts.
     *
     * @param  list<array{int, 'debit'|'credit', string}>  $specs
     * @return list<PostableLine>
     */
    private function lines(Bill $bill, array $specs, ?string $memo): array
    {
        $lines = [];

        foreach ($specs as [$accountId, $side, $amount]) {
            if (! Money::isPositive($amount)) {
                continue;
            }

            $lines[] = new PostableLine(
                accountId: $accountId,
                debit: $side === 'debit' ? $amount : '0.00',
                credit: $side === 'credit' ? $amount : '0.00',
                studentId: $bill->student_id,
                billId: $bill->id,
                memo: $memo,
            );
        }

        return $lines;
    }
}
