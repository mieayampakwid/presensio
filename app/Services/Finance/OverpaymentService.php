<?php

namespace App\Services\Finance;

use App\Enums\AccountType;
use App\Enums\JournalSource;
use App\Enums\OverpaymentReason;
use App\Enums\OverpaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Guardian;
use App\Models\JournalEntry;
use App\Models\Overpayment;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Finance\Exceptions\OverpaymentOperationException;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OverpaymentService
{
    public function __construct(
        private readonly JournalPoster $poster,
        private readonly SchoolSettings $settings,
        private readonly PeriodService $periods,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{guardian_id: int, student_id?: int|null, related_payment_id?: int|null, amount: string, bank_mutation_date: string, posting_date: string, deposit_account_id: int, reason: string, note?: string|null, proof_path: string, proof_hash: string}  $data
     */
    public function record(array $data, User $actor): Overpayment
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($data, $actor) {
            $liabilityId = $this->settings->guardianDepositAccountId();
            $this->require($liabilityId !== null, 'Akun Titipan Wali belum diatur. Minta admin mengaturnya di Profil Sekolah.');
            $this->liabilityAccount($liabilityId);
            $this->bankAccount($data['deposit_account_id']);
            $amount = Money::toDecimal($data['amount']);
            $this->require(Money::isPositive($amount), 'Jumlah kelebihan pembayaran harus lebih besar dari nol.');
            $this->require($data['posting_date'] >= $data['bank_mutation_date'], 'Tanggal posting tidak boleh sebelum tanggal mutasi bank.');
            $this->require($this->periods->isOpen($data['posting_date']), 'Periode tanggal posting telah ditutup.');
            $guardian = Guardian::find($data['guardian_id']);
            $this->require($guardian !== null, 'Wali wajib dipilih.');
            if (! empty($data['student_id'])) {
                $this->require($guardian->students()->whereKey($data['student_id'])->exists(), 'Siswa tidak terhubung dengan wali ini.');
            }
            if (! empty($data['related_payment_id'])) {
                $payment = Payment::query()->lockForUpdate()->find($data['related_payment_id']);
                $this->require($payment !== null && $payment->status === PaymentStatus::Verified && $payment->payment_method === PaymentMethod::BankTransfer, 'Pembayaran terkait harus berupa transfer bank terverifikasi.');
                $students = $payment->allocations()->join('bills', 'bills.id', '=', 'payment_allocations.bill_id')->pluck('bills.student_id')->unique();
                $this->require($students->isNotEmpty() && $guardian->students()->whereIn('students.id', $students)->count() === $students->count(), 'Seluruh siswa dalam pembayaran terkait harus terhubung dengan wali.');
            }
            $this->require(trim($data['proof_path']) !== '' && preg_match('/^[a-f0-9]{64}$/', $data['proof_hash']) === 1, 'Bukti mutasi bank wajib dilampirkan.');
            $this->require(OverpaymentReason::tryFrom($data['reason']) !== null, 'Alasan kelebihan pembayaran tidak valid.');
            $record = Overpayment::create([
                'guardian_id' => $data['guardian_id'], 'student_id' => $data['student_id'] ?? null,
                'related_payment_id' => $data['related_payment_id'] ?? null,
                'liability_account_id' => $liabilityId, 'deposit_account_id' => $data['deposit_account_id'],
                'amount' => $amount, 'bank_mutation_date' => $data['bank_mutation_date'], 'posting_date' => $data['posting_date'],
                'reason' => $data['reason'], 'status' => OverpaymentStatus::Held, 'note' => $data['note'] ?? null,
                'proof_path' => $data['proof_path'], 'proof_hash' => $data['proof_hash'], 'recorded_by_user_id' => $actor->id,
            ]);
            $entry = $this->poster->post(new PostableEntry(
                entryDate: $data['posting_date'], description: "Penerimaan kelebihan bayar #{$record->id} — {$guardian->name}",
                sourceType: JournalSource::OverpaymentReceipt,
                lines: [new PostableLine($record->deposit_account_id, debit: $amount, studentId: $record->student_id), new PostableLine($liabilityId, credit: $amount, studentId: $record->student_id)],
                sourceId: $record->id, createdByUserId: $actor->id,
            ));
            $record->update(['receipt_entry_id' => $entry->id]);
            $this->audit->record($record, 'overpayment_recorded', null, $record->getAttributes());

            return $record;
        });
    }

    /**
     * @param  array{refund_date: string, refund_account_id: int, bank_fee?: string, bank_reference: string, net_refund?: string, bank_total?: string, refund_proof_path?: string|null, refund_proof_hash?: string|null}  $data
     */
    public function refund(Overpayment $overpayment, array $data, User $actor): Overpayment
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($overpayment, $data, $actor) {
            $record = $this->lockHeld($overpayment);
            $this->liabilityAccount($record->liability_account_id);
            $this->bankAccount($data['refund_account_id']);
            $this->require($data['refund_date'] >= $record->posting_date->toDateString(), 'Tanggal refund tidak boleh sebelum tanggal posting penerimaan.');
            $this->require($this->periods->isOpen($data['refund_date']), 'Periode tanggal refund telah ditutup.');
            $this->require(trim($data['bank_reference']) !== '', 'Referensi bank wajib diisi.');
            $fee = Money::toDecimal($data['bank_fee'] ?? '0');
            $this->require(! Money::isNegative($fee) && Money::lt($fee, $record->amount), 'Biaya bank harus nol atau lebih dan kurang dari nilai titipan.');
            $net = Money::sub($record->amount, $fee);
            foreach (['net_refund' => $net, 'bank_total' => $record->amount] as $key => $expected) {
                $this->require(! isset($data[$key]) || Money::cmp($data[$key], $expected) === 0, 'Nominal bersih atau total bank tidak sesuai perhitungan server.');
            }
            $lines = [
                new PostableLine($record->liability_account_id, debit: $record->amount, studentId: $record->student_id),
                new PostableLine($data['refund_account_id'], credit: $net, studentId: $record->student_id, memo: 'Dana bersih ke wali'),
            ];
            if (Money::isPositive($fee)) {
                $lines[] = new PostableLine($data['refund_account_id'], credit: $fee, studentId: $record->student_id, memo: 'Biaya bank ditanggung wali');
            }
            $entry = $this->poster->post(new PostableEntry(
                entryDate: $data['refund_date'], description: "Pengembalian kelebihan bayar #{$record->id} — {$data['bank_reference']}",
                sourceType: JournalSource::OverpaymentRefund, lines: $lines, sourceId: $record->id, createdByUserId: $actor->id,
            ));
            $record->update([
                'status' => OverpaymentStatus::Refunded, 'refund_entry_id' => $entry->id,
                'refund_account_id' => $data['refund_account_id'], 'refund_date' => $data['refund_date'],
                'bank_reference' => $data['bank_reference'], 'bank_fee' => $fee, 'net_refund' => $net,
                'refund_proof_path' => $data['refund_proof_path'] ?? null, 'refund_proof_hash' => $data['refund_proof_hash'] ?? null,
                'refunded_by_user_id' => $actor->id, 'refunded_at' => now(),
            ]);
            $this->audit->record($record, 'overpayment_refunded', ['status' => 'held'], [...$record->getAttributes(), 'bank_total' => $record->amount]);

            return $record;
        });
    }

    public function cancel(Overpayment $overpayment, string $date, string $reason, User $actor): Overpayment
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($overpayment, $date, $reason, $actor) {
            $record = $this->lockHeld($overpayment);
            $this->require(trim($reason) !== '', 'Alasan pembatalan wajib diisi.');
            $this->require($date >= $record->posting_date->toDateString(), 'Tanggal pembatalan tidak boleh sebelum tanggal posting penerimaan.');
            $this->liabilityAccount($record->liability_account_id);
            $this->bankAccount($record->deposit_account_id);
            $this->require($this->periods->nextOpenPeriodStart($date)->toDateString() >= $record->posting_date->toDateString(), 'Tanggal efektif pembatalan tidak valid.');
            $entry = $this->poster->reverse(JournalEntry::findOrFail($record->receipt_entry_id), Carbon::parse($date), $reason);
            $record->update([
                'status' => OverpaymentStatus::Cancelled, 'requested_cancel_date' => $date, 'cancel_reason' => $reason,
                'cancellation_entry_id' => $entry->id, 'cancelled_by_user_id' => $actor->id, 'cancelled_at' => now(),
            ]);
            $this->audit->record($record, 'overpayment_cancelled', ['status' => 'held'], [...$record->getAttributes(), 'effective_date' => $entry->entry_date->toDateString()], $reason);

            return $record;
        });
    }

    private function lockHeld(Overpayment $record): Overpayment
    {
        $locked = Overpayment::query()->lockForUpdate()->findOrFail($record->id);
        $this->require($locked->status === OverpaymentStatus::Held, 'Hanya kelebihan pembayaran berstatus dititipkan yang dapat diproses.');

        return $locked;
    }

    private function liabilityAccount(int $id): void
    {
        $account = Account::find($id);
        $this->require($account !== null && $account->type === AccountType::Liability && ! $account->is_cash && ! $account->is_contra && $account->is_active && $account->is_postable && $id !== $this->settings->row()->prepaid_revenue_account_id, 'Akun Titipan Wali tidak valid atau tidak aktif. Minta admin mengaktifkan kembali akun asli; akun tidak diganti otomatis.');
    }

    private function bankAccount(int $id): void
    {
        $account = Account::find($id);
        $this->require($account !== null && $account->type === AccountType::Asset && $account->is_cash && ! $account->is_contra && $account->is_active && $account->is_postable, 'Pilih akun kas/bank aset aktif, postable, dan bukan kontra.');
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasAnyRole(UserRole::Admin, UserRole::Finance), 403);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new OverpaymentOperationException($message);
        }
    }
}
