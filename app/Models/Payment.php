<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Money movement header; allocations split it over bills (spec 14 §Fee operations).
 *
 * Once verified, a payment is immutable except for the void stamp.
 *
 * @property int $id
 * @property string|null $receipt_number
 * @property string $amount
 * @property PaymentMethod $payment_method
 * @property int $deposit_account_id
 * @property Carbon $payment_date
 * @property string|null $origin_bank
 * @property string|null $proof_path
 * @property string|null $proof_hash
 * @property PaymentStatus $status
 * @property string|null $reject_reason
 * @property string|null $void_reason
 * @property string|null $notes
 * @property int $submitted_by_user_id
 * @property int|null $verified_by_user_id
 * @property Carbon|null $verified_at
 * @property int|null $voided_by_user_id
 * @property Carbon|null $voided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $depositAccount
 * @property-read User $submittedBy
 * @property-read User|null $verifiedBy
 * @property-read User|null $voidedBy
 * @property-read Collection<int, PaymentAllocation> $allocations
 */
#[Fillable([
    'receipt_number',
    'amount',
    'payment_method',
    'deposit_account_id',
    'payment_date',
    'origin_bank',
    'proof_path',
    'proof_hash',
    'status',
    'reject_reason',
    'void_reason',
    'notes',
    'submitted_by_user_id',
    'verified_by_user_id',
    'verified_at',
    'voided_by_user_id',
    'voided_at',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Columns that may change when a verified payment is voided.
     *
     * @var list<string>
     */
    private const VOID_STAMP_FIELDS = ['status', 'void_reason', 'voided_at', 'voided_by_user_id', 'updated_at'];

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            $original = $payment->getOriginal('status');

            if (! in_array($original, [PaymentStatus::Verified, PaymentStatus::Void], true)) {
                return;
            }

            $changed = array_keys($payment->getDirty());

            if (
                $original === PaymentStatus::Verified
                && $payment->status === PaymentStatus::Void
                && array_diff($changed, self::VOID_STAMP_FIELDS) === []
            ) {
                return;
            }

            throw new LogicException('Verified payments are immutable except for the void stamp.');
        });

        static::deleting(function (Payment $payment): void {
            if (in_array($payment->status, [PaymentStatus::Verified, PaymentStatus::Void], true)) {
                throw new LogicException('Verified payments cannot be deleted.');
            }
        });
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function depositAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deposit_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * Allocations splitting this payment over bills.
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'payment_date' => 'date',
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
