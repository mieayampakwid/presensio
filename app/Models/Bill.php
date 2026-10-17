<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Support\Money;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single receivable owed by a student (spec 14 §Fee operations).
 *
 * `paid_amount` and `status` are cached values derived from verified allocations.
 *
 * @property int $id
 * @property int $student_id
 * @property int $fee_type_id
 * @property int $academic_year_id
 * @property string $period_key
 * @property string $title
 * @property string $amount
 * @property string $discount_amount
 * @property string|null $discount_reason
 * @property string $paid_amount
 * @property BillStatus $status
 * @property Carbon|null $recognized_at
 * @property bool $is_opening
 * @property Carbon|null $written_off_at
 * @property string|null $write_off_reason
 * @property Carbon $due_date
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read FeeType $feeType
 * @property-read AcademicYear $academicYear
 * @property-read Collection<int, PaymentAllocation> $allocations
 */
#[Fillable([
    'student_id',
    'fee_type_id',
    'academic_year_id',
    'period_key',
    'title',
    'amount',
    'discount_amount',
    'discount_reason',
    'paid_amount',
    'status',
    'recognized_at',
    'is_opening',
    'written_off_at',
    'write_off_reason',
    'due_date',
    'cancelled_at',
    'cancel_reason',
])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Payment allocations applied to this bill.
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Net payable amount: gross minus discount.
     */
    public function netAmount(): string
    {
        return Money::sub($this->amount, $this->discount_amount);
    }

    /**
     * Amount still collectible: net minus paid, never negative.
     */
    public function remainingAmount(): string
    {
        $remaining = Money::sub($this->netAmount(), $this->paid_amount);

        return Money::isNegative($remaining) ? '0.00' : $remaining;
    }

    /**
     * Whether the recognition (accrual) entry has been posted.
     */
    public function isRecognized(): bool
    {
        return $this->recognized_at !== null;
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
            'discount_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => BillStatus::class,
            'recognized_at' => 'date',
            'is_opening' => 'boolean',
            'written_off_at' => 'datetime',
            'due_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }
}
