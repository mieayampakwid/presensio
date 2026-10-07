<?php

namespace App\Models;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Support\Money;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Double-entry general ledger entry (spec 14 §Journals).
 *
 * @property int $id
 * @property string|null $number
 * @property Carbon $entry_date
 * @property string $description
 * @property JournalStatus $status
 * @property JournalSource $source_type
 * @property int|null $source_id
 * @property int|null $reverses_entry_id
 * @property string|null $attachment_path
 * @property int|null $created_by_user_id
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, JournalLine> $lines
 * @property-read JournalEntry|null $reversesEntry
 * @property-read Collection<int, JournalEntry> $reversalEntries
 * @property-read User|null $author
 */
#[Fillable([
    'number',
    'entry_date',
    'description',
    'status',
    'source_type',
    'source_id',
    'reverses_entry_id',
    'attachment_path',
    'created_by_user_id',
    'posted_at',
])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (JournalEntry $entry) {
            $originalStatus = $entry->getOriginal('status');
            $originalStatusValue = $originalStatus instanceof JournalStatus
                ? $originalStatus->value
                : (string) $originalStatus;

            if ($originalStatusValue === JournalStatus::Posted->value) {
                throw new RuntimeException('Posted journal entries cannot be modified.');
            }
        });

        static::deleting(function (JournalEntry $entry) {
            if ($entry->status === JournalStatus::Posted) {
                throw new RuntimeException('Posted journal entries cannot be deleted.');
            }
        });
    }

    /**
     * Lines belonging to this journal entry.
     *
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * The journal entry this entry reverses, if any.
     *
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversesEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reverses_entry_id');
    }

    /**
     * Journal entries reversing this entry.
     *
     * @return HasMany<JournalEntry, $this>
     */
    public function reversalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reverses_entry_id');
    }

    /**
     * The user who authored this journal entry.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Calculate total debit across lines.
     */
    public function totalDebit(): string
    {
        return $this->lines->reduce(
            fn (string $carry, JournalLine $line) => Money::add($carry, $line->debit),
            '0.00'
        );
    }

    /**
     * Calculate total credit across lines.
     */
    public function totalCredit(): string
    {
        return $this->lines->reduce(
            fn (string $carry, JournalLine $line) => Money::add($carry, $line->credit),
            '0.00'
        );
    }

    /**
     * Check whether total debits equal total credits.
     */
    public function isBalanced(): bool
    {
        return Money::cmp($this->totalDebit(), $this->totalCredit()) === 0;
    }

    /**
     * Check if this entry is a posted entry.
     */
    public function isPosted(): bool
    {
        return $this->status === JournalStatus::Posted;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalStatus::class,
            'source_type' => JournalSource::class,
            'source_id' => 'integer',
            'reverses_entry_id' => 'integer',
            'created_by_user_id' => 'integer',
            'posted_at' => 'datetime',
        ];
    }
}
