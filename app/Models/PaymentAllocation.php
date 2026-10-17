<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Portion of a payment applied to one bill. Immutable once the payment is verified.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $bill_id
 * @property string $amount
 * @property Carbon|null $created_at
 * @property-read Payment $payment
 * @property-read Bill $bill
 */
#[Fillable([
    'payment_id',
    'bill_id',
    'amount',
])]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        $guard = function (PaymentAllocation $allocation): void {
            $status = Payment::query()->find($allocation->payment_id)?->status;

            if (in_array($status, [PaymentStatus::Verified, PaymentStatus::Void], true)) {
                throw new LogicException('Allocations of a verified payment are immutable.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
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
        ];
    }
}
