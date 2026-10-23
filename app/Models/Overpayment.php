<?php

namespace App\Models;

use App\Enums\OverpaymentReason;
use App\Enums\OverpaymentStatus;
use Database\Factories\OverpaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $guardian_id
 * @property int|null $student_id
 * @property int|null $related_payment_id
 * @property int $liability_account_id
 * @property int $deposit_account_id
 * @property string $amount
 * @property Carbon $bank_mutation_date
 * @property Carbon $posting_date
 * @property OverpaymentReason $reason
 * @property OverpaymentStatus $status
 * @property string|null $note
 * @property string $proof_path
 * @property string $proof_hash
 * @property int|null $receipt_entry_id
 * @property int|null $refund_entry_id
 * @property int|null $cancellation_entry_id
 * @property Carbon|null $refund_date
 * @property Carbon|null $requested_cancel_date
 * @property int|null $refund_account_id
 * @property string|null $bank_fee
 * @property string|null $net_refund
 * @property string|null $bank_reference
 * @property string|null $refund_proof_path
 * @property string|null $refund_proof_hash
 * @property string|null $cancel_reason
 * @property int $recorded_by_user_id
 * @property int|null $refunded_by_user_id
 * @property int|null $cancelled_by_user_id
 * @property Carbon|null $refunded_at
 * @property Carbon|null $cancelled_at
 * @property-read Guardian|null $guardian
 * @property-read Student|null $student
 * @property-read Payment|null $relatedPayment
 * @property-read Account|null $liabilityAccount
 * @property-read Account|null $depositAccount
 * @property-read Account|null $refundAccount
 * @property-read JournalEntry|null $receiptEntry
 * @property-read JournalEntry|null $refundEntry
 * @property-read JournalEntry|null $cancellationEntry
 * @property-read User|null $recordedBy
 * @property-read User|null $refundedBy
 * @property-read User|null $cancelledBy
 */
#[Fillable(['guardian_id', 'student_id', 'related_payment_id', 'liability_account_id', 'deposit_account_id', 'amount', 'bank_mutation_date', 'posting_date', 'reason', 'status', 'note', 'proof_path', 'proof_hash', 'receipt_entry_id', 'refund_entry_id', 'cancellation_entry_id', 'refund_date', 'requested_cancel_date', 'refund_account_id', 'bank_fee', 'net_refund', 'bank_reference', 'refund_proof_path', 'refund_proof_hash', 'cancel_reason', 'recorded_by_user_id', 'refunded_by_user_id', 'cancelled_by_user_id', 'refunded_at', 'cancelled_at'])]
class Overpayment extends Model
{
    /** @use HasFactory<OverpaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (Overpayment $record): void {
            if ($record->getOriginal('receipt_entry_id') === null) {
                if (array_diff(array_keys($record->getDirty()), ['receipt_entry_id', 'updated_at']) === []) {
                    return;
                }
            } elseif ($record->getOriginal('status') === OverpaymentStatus::Held) {
                $allowed = match ($record->status) {
                    OverpaymentStatus::Refunded => ['status', 'refund_entry_id', 'refund_date', 'refund_account_id', 'bank_fee', 'net_refund', 'bank_reference', 'refund_proof_path', 'refund_proof_hash', 'refunded_by_user_id', 'refunded_at', 'updated_at'],
                    OverpaymentStatus::Cancelled => ['status', 'cancellation_entry_id', 'requested_cancel_date', 'cancel_reason', 'cancelled_by_user_id', 'cancelled_at', 'updated_at'],
                    default => [],
                };
                if ($allowed !== [] && array_diff(array_keys($record->getDirty()), $allowed) === []) {
                    return;
                }
            }
            throw new LogicException('Overpayment facts are immutable after recording.');
        });
        static::deleting(function (Overpayment $record): void {
            if ($record->receipt_entry_id !== null) {
                throw new LogicException('Posted overpayments cannot be deleted.');
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'bank_fee' => 'decimal:2', 'net_refund' => 'decimal:2',
            'status' => OverpaymentStatus::class, 'reason' => OverpaymentReason::class,
            'bank_mutation_date' => 'date', 'posting_date' => 'date', 'refund_date' => 'date',
            'requested_cancel_date' => 'date', 'refunded_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Guardian, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class, 'guardian_id');
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function relatedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function receiptEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'receipt_entry_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function refundEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'refund_entry_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function cancellationEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cancellation_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }
}
