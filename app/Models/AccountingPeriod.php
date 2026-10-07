<?php

namespace App\Models;

use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Monthly accounting period record (spec 14 §Accounting periods & closing).
 *
 * @property string $period
 * @property string $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $closedBy
 */
#[Fillable([
    'period',
    'status',
    'closed_at',
    'closed_by_user_id',
])]
class AccountingPeriod extends Model
{
    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory;

    protected $primaryKey = 'period';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * User who closed the period.
     *
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Check if the period is open.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'closed_by_user_id' => 'integer',
        ];
    }
}
