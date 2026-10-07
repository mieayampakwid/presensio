<?php

namespace App\Models;

use App\Enums\JournalStatus;
use Database\Factories\JournalLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Double-entry journal line item (spec 14 §Journals).
 *
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property string $debit
 * @property string $credit
 * @property int|null $student_id
 * @property int|null $bill_id
 * @property string|null $memo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JournalEntry $journalEntry
 * @property-read Account $account
 * @property-read Student|null $student
 */
#[Fillable([
    'journal_entry_id',
    'account_id',
    'debit',
    'credit',
    'student_id',
    'bill_id',
    'memo',
])]
class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (JournalLine $line) {
            $entry = $line->journalEntry ?? JournalEntry::find($line->journal_entry_id);
            if ($entry?->status === JournalStatus::Posted) {
                throw new RuntimeException('Posted journal lines cannot be modified.');
            }
        });

        static::deleting(function (JournalLine $line) {
            $entry = $line->journalEntry ?? JournalEntry::find($line->journal_entry_id);
            if ($entry?->status === JournalStatus::Posted) {
                throw new RuntimeException('Posted journal lines cannot be deleted.');
            }
        });
    }

    /**
     * Parent journal entry.
     *
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Account booked in this line.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Optional student subledger recipient.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'journal_entry_id' => 'integer',
            'account_id' => 'integer',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'student_id' => 'integer',
            'bill_id' => 'integer',
        ];
    }
}
