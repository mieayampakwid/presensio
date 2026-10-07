<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NetAssetRestriction;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Chart of accounts master record (spec 14 §Chart of accounts).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property bool $is_contra
 * @property NetAssetRestriction|null $restriction
 * @property int|null $parent_id
 * @property bool $is_postable
 * @property bool $is_cash
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $parent
 * @property-read Collection<int, Account> $children
 * @property-read Collection<int, JournalLine> $journalLines
 */
#[Fillable([
    'code',
    'name',
    'type',
    'is_contra',
    'restriction',
    'parent_id',
    'is_postable',
    'is_cash',
    'is_active',
])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * Parent account in the chart hierarchy.
     *
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * Child accounts under this grouping account.
     *
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * Posted and unposted journal lines booked to this account.
     *
     * @return HasMany<JournalLine, $this>
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Check if this account has any posted journal lines.
     */
    public function hasPostedLines(): bool
    {
        return $this->journalLines()
            ->whereHas('journalEntry', function ($query) {
                $query->where('status', 'posted');
            })
            ->exists();
    }

    /**
     * Resolve effective normal balance (debit or credit), taking contra flag into account.
     */
    public function normalBalance(): string
    {
        $base = $this->type->normalBalance();

        if ($this->is_contra) {
            return $base === 'debit' ? 'credit' : 'debit';
        }

        return $base;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'restriction' => NetAssetRestriction::class,
            'is_contra' => 'boolean',
            'is_postable' => 'boolean',
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
            'parent_id' => 'integer',
        ];
    }
}
