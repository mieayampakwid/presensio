<?php

namespace App\Models;

use App\Enums\FeeKind;
use Database\Factories\FeeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fee catalog entry with its three account mappings (spec 14 §Fee operations).
 *
 * @property int $id
 * @property int|null $academic_year_id
 * @property string $name
 * @property string $code
 * @property FeeKind $type
 * @property string $default_amount
 * @property string|null $description
 * @property int $receivable_account_id
 * @property int $revenue_account_id
 * @property int $discount_account_id
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear|null $academicYear
 * @property-read Account $receivableAccount
 * @property-read Account $revenueAccount
 * @property-read Account $discountAccount
 */
#[Fillable([
    'academic_year_id',
    'name',
    'code',
    'type',
    'default_amount',
    'description',
    'receivable_account_id',
    'revenue_account_id',
    'discount_account_id',
    'is_active',
])]
class FeeType extends Model
{
    /** @use HasFactory<FeeTypeFactory> */
    use HasFactory;

    /**
     * The academic year this fee type is scoped to, if any.
     *
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Receivable (Piutang) account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    /**
     * Revenue account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function revenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'revenue_account_id');
    }

    /**
     * Contra-revenue discount account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function discountAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'discount_account_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeeKind::class,
            'default_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
