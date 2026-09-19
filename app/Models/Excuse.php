<?php

namespace App\Models;

use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use Database\Factories\ExcuseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A guardian-submitted absence excuse covering one child and one date
 * range (single day = 1-day range). Approval injects/overwrites
 * attendance rows for the school days inside that range (spec 04).
 *
 * @property int $id
 * @property int $student_id
 * @property ExcuseType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property string|null $attachment_path
 * @property ExcuseStatus $status
 * @property string|null $review_note
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'student_id',
    'type',
    'start_date',
    'end_date',
    'reason',
    'attachment_path',
    'status',
    'review_note',
    'reviewed_by_user_id',
])]
class Excuse extends Model
{
    /** @use HasFactory<ExcuseFactory> */
    use HasFactory;

    /**
     * Child this excuse covers.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Admin who approved or rejected the excuse.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExcuseType::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => ExcuseStatus::class,
        ];
    }
}
