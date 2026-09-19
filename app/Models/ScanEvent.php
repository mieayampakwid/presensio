<?php

namespace App\Models;

use App\Enums\ScanMethod;
use App\Enums\ScanOutcome;
use Database\Factories\ScanEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only record of one scan attempt (spec 03 §Schema). Never updated
 * after creation — the Exception Dashboard edits attendances, not events.
 *
 * @property int $id
 * @property int|null $student_id
 * @property ScanMethod $scan_method
 * @property string|null $identifier
 * @property Carbon $scanned_at
 * @property ScanOutcome $outcome
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['student_id', 'scan_method', 'identifier', 'scanned_at', 'outcome'])]
class ScanEvent extends Model
{
    /** @use HasFactory<ScanEventFactory> */
    use HasFactory;

    /**
     * Resolved student; null when the credential matched nobody.
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
            'scan_method' => ScanMethod::class,
            'scanned_at' => 'datetime',
            'outcome' => ScanOutcome::class,
        ];
    }
}
