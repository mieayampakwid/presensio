<?php

namespace App\Services\Finance;

use App\Enums\JournalSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Exceptions\PaymentOperationException;
use App\Services\SchoolSettings;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * Payment lifecycle against the ledger (spec 14 §Fee operations). Lock order is always
 * payment first, then bills ordered by id.
 */
class PaymentService
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly PeriodService $periodService,
        private readonly AllocationValidator $validator,
        private readonly ReceiptNumberGenerator $receiptNumbers,
        private readonly BillService $billService,
        private readonly SchoolSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Cash is created directly as verified; the amount is the sum of the allocations.
     *
     * @param  array<int, string>  $allocations  bill id => amount
     * @param  list<int>  $studentIds  students chosen together by the cashier
     */
    public function createCash(array $allocations, array $studentIds, int $depositAccountId, User $cashier, ?string $notes = null): Payment
    {
        return DB::transaction(function () use ($allocations, $studentIds, $depositAccountId, $cashier, $notes) {
            $amount = Money::toDecimal(array_reduce($allocations, fn (string $sum, $part) => Money::add($sum, $part), '0.00'));
            $bills = $this->validator->validate($amount, $allocations, $studentIds);
            $today = $this->today();

            $payment = Payment::create([
                'receipt_number' => $this->receiptNumbers->generate($today),
                'amount' => $amount,
                'payment_method' => PaymentMethod::Cash,
                'deposit_account_id' => $depositAccountId,
                'payment_date' => $today,
                'status' => PaymentStatus::Verified,
                'notes' => $notes,
                'submitted_by_user_id' => $cashier->id,
                'verified_by_user_id' => $cashier->id,
                'verified_at' => now(),
            ]);

            $this->settle($payment, $allocations, $bills, $cashier, $today);

            return $payment;
        });
    }

    /**
     * Guardian transfer upload: pending, nothing posted, bills keep their status (AC-14-06).
     *
     * @param  array<int, string>  $allocations  bill id => amount
     * @param  list<int>  $studentIds  students linked to the paying guardian
     */
    public function submitTransfer(
        string $amount,
        array $allocations,
        array $studentIds,
        int $depositAccountId,
        CarbonInterface|string $paymentDate,
        User $submitter,
        ?string $originBank = null,
        ?string $proofPath = null,
        ?string $proofHash = null,
        ?string $notes = null,
    ): Payment {
        return DB::transaction(function () use ($amount, $allocations, $studentIds, $depositAccountId, $paymentDate, $submitter, $originBank, $proofPath, $proofHash, $notes) {
            $amount = Money::toDecimal($amount);
            $this->validator->validate($amount, $allocations, $studentIds);

            $payment = Payment::create([
                'amount' => $amount,
                'payment_method' => PaymentMethod::BankTransfer,
                'deposit_account_id' => $depositAccountId,
                'payment_date' => $paymentDate,
                'origin_bank' => $originBank,
                'proof_path' => $proofPath,
                'proof_hash' => $proofHash,
                'status' => PaymentStatus::Pending,
                'notes' => $notes,
                'submitted_by_user_id' => $submitter->id,
            ]);

            foreach ($allocations as $billId => $part) {
                $payment->allocations()->create(['bill_id' => $billId, 'amount' => Money::toDecimal($part)]);
            }

            return $payment;
        });
    }

    /**
     * Verify a pending payment, optionally replacing its allocations first. Re-allocation may
     * target the payment's current students or any child of the submitting guardian.
     *
     * @param  array<int, string>|null  $reallocation  bill id => amount
     */
    public function verify(Payment $payment, ?array $reallocation, int $depositAccountId, User $verifier): Payment
    {
        return DB::transaction(function () use ($payment, $reallocation, $depositAccountId, $verifier) {
            $payment = $this->lockPending($payment);
            $payment->load('allocations.bill');

            $existing = $payment->allocations->pluck('amount', 'bill_id')->all();
            $allocations = $reallocation ?? $existing;
            $guardianStudents = Guardian::query()->where('user_id', $payment->submitted_by_user_id)->with('students:id')->get()
                ->flatMap(fn (Guardian $guardian) => $guardian->students->pluck('id'));
            $studentIds = array_values($payment->allocations->map(fn ($allocation) => $allocation->bill->student_id)->merge($guardianStudents)->map(fn ($id) => (int) $id)->unique()->all());

            $bills = $this->validator->validate($payment->amount, $allocations, $studentIds);

            if ($reallocation !== null) {
                $payment->allocations()->delete();
            }

            $today = $this->today();
            $payment->update([
                'receipt_number' => $this->receiptNumbers->generate($today),
                'deposit_account_id' => $depositAccountId,
                'verified_by_user_id' => $verifier->id,
                'verified_at' => now(),
                'status' => PaymentStatus::Verified,
            ]);

            $this->settle($payment, $reallocation ?? [], $bills, $verifier, $today);

            $this->auditLogger->record($payment, 'verified', ['status' => PaymentStatus::Pending->value], ['status' => PaymentStatus::Verified->value, 'receipt_number' => $payment->receipt_number]);

            return $payment;
        });
    }

    /**
     * Reject a pending payment; nothing is posted and the bills are recomputed.
     */
    public function reject(Payment $payment, string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $reason) {
            if (trim($reason) === '') {
                throw new PaymentOperationException('Alasan penolakan wajib diisi.');
            }

            $payment = $this->lockPending($payment);
            $payment->update(['status' => PaymentStatus::Rejected, 'reject_reason' => $reason]);

            Bill::query()->whereIn('id', $payment->allocations()->select('bill_id'))->orderBy('id')->lockForUpdate()->get()
                ->each(fn (Bill $bill) => $this->billService->recompute($bill));

            $this->auditLogger->record($payment, 'rejected', ['status' => PaymentStatus::Pending->value], ['status' => PaymentStatus::Rejected->value], $reason);

            return $payment;
        });
    }

    /**
     * Void a verified payment (AC-14-08): reverse the verification entry on the void date, reclassify prepaid
     * credits whose bill was recognized since (Dr receivable / Cr prepaid revenue, so the plain reversal does not
     * leave prepaid negative), then recompute the bills.
     */
    public function void(Payment $payment, string $reason, User $user): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $user) {
            if (trim($reason) === '') {
                throw new PaymentOperationException('Alasan pembatalan wajib diisi.');
            }

            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Verified) {
                throw new PaymentOperationException('Hanya pembayaran terverifikasi yang dapat dibatalkan.');
            }

            $bills = Bill::query()->with('feeType')->whereIn('id', $payment->allocations()->select('bill_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            // The write-off only cleared the outstanding amount, so reversing this payment would revive a receivable on a written-off bill.
            if ($bills->contains(fn (Bill $bill) => $bill->written_off_at !== null)) {
                throw new PaymentOperationException('Pembayaran untuk tagihan yang sudah dihapusbukukan tidak dapat dibatalkan.');
            }

            $original = JournalEntry::query()->with('lines')
                ->where('source_type', JournalSource::Payment)->where('source_id', $payment->id)->whereNull('reverses_entry_id')
                ->firstOrFail();

            $reversal = $this->poster->reverse($original, $this->today(), $reason);
            $this->reclassifyRecognizedPrepaid($original, $reversal, $bills, $user);

            $payment->update([
                'status' => PaymentStatus::Void,
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by_user_id' => $user->id,
            ]);

            $bills->each(fn (Bill $bill) => $this->billService->recompute($bill));

            $this->auditLogger->record($payment, 'voided', ['status' => PaymentStatus::Verified->value], ['status' => PaymentStatus::Void->value], $reason);

            return $payment;
        });
    }

    /**
     * Allocations that credited prepaid revenue on a bill recognized afterwards had that prepaid consumed by
     * the recognition (Dr P), so the reversal's Dr P overshoots: move it back with Dr R / Cr P.
     *
     * @param  SupportCollection<int, Bill>  $bills
     */
    private function reclassifyRecognizedPrepaid(JournalEntry $original, JournalEntry $reversal, SupportCollection $bills, User $user): void
    {
        $prepaidId = $this->prepaidAccountId();
        $lines = [];

        foreach ($original->lines->where('account_id', $prepaidId)->sortBy('bill_id') as $line) {
            $bill = $bills->get($line->bill_id);

            if (! $bill->isRecognized()) {
                continue;
            }

            $lines[] = new PostableLine(accountId: $bill->feeType->receivable_account_id, debit: $line->credit, credit: '0.00', studentId: $bill->student_id, billId: $bill->id);
            $lines[] = new PostableLine(accountId: $prepaidId, debit: '0.00', credit: $line->credit, studentId: $bill->student_id, billId: $bill->id);
        }

        if ($lines === []) {
            return;
        }

        $this->poster->post(new PostableEntry(
            entryDate: $reversal->entry_date,
            description: "Reklasifikasi pembatalan: {$original->description}",
            sourceType: JournalSource::PaymentVoid,
            lines: $lines,
            sourceId: $original->source_id,
            createdByUserId: $user->id,
        ));
    }

    /**
     * Other non-rejected payments carrying the same proof hash (AC-14-10).
     *
     * @return Collection<int, Payment>
     */
    public function duplicateProofWarnings(Payment $payment): Collection
    {
        if ($payment->proof_hash === null) {
            return new Collection;
        }

        return Payment::query()
            ->where('proof_hash', $payment->proof_hash)
            ->whereKeyNot($payment->id)
            ->where('status', '!=', PaymentStatus::Rejected)
            ->get();
    }

    /**
     * Create missing allocation rows (when $allocations is non-empty), post the entry, recompute bills.
     * The payment must already be verified, so allocations are created after the status is set.
     *
     * @param  array<int, string>  $allocations  rows to insert; empty when they already exist
     * @param  SupportCollection<int, Bill>  $bills
     */
    private function settle(Payment $payment, array $allocations, SupportCollection $bills, User $user, CarbonInterface $today): void
    {
        foreach ($allocations as $billId => $part) {
            $payment->allocations()->create(['bill_id' => $billId, 'amount' => Money::toDecimal($part)]);
        }

        $payment->load('allocations');
        $lines = [new PostableLine(accountId: $payment->deposit_account_id, debit: $payment->amount, credit: '0.00')];

        foreach ($payment->allocations->sortBy('bill_id') as $allocation) {
            $bill = $bills->get($allocation->bill_id);

            $lines[] = new PostableLine(
                accountId: $bill->isRecognized() ? $bill->feeType->receivable_account_id : $this->prepaidAccountId(),
                debit: '0.00',
                credit: $allocation->amount,
                studentId: $bill->student_id,
                billId: $bill->id,
            );
        }

        $date = $this->periodService->nextOpenPeriodStart($today);

        $this->poster->post(new PostableEntry(
            entryDate: $date,
            description: "Pembayaran {$payment->receipt_number}",
            sourceType: JournalSource::Payment,
            lines: $lines,
            sourceId: $payment->id,
            createdByUserId: $user->id,
        ));

        $bills->each(fn (Bill $bill) => $this->billService->recompute($bill));
    }

    private function lockPending(Payment $payment): Payment
    {
        $locked = Payment::query()->lockForUpdate()->findOrFail($payment->id);

        if ($locked->status !== PaymentStatus::Pending) {
            throw new PaymentOperationException('Hanya pembayaran berstatus menunggu verifikasi yang dapat diproses.');
        }

        return $locked;
    }

    private function prepaidAccountId(): int
    {
        return $this->settings->row()->prepaid_revenue_account_id
            ?? throw new PaymentOperationException('Akun pendapatan diterima di muka belum diatur di pengaturan sekolah.');
    }

    private function today(): CarbonInterface
    {
        return Date::parse($this->settings->todayDate());
    }
}
