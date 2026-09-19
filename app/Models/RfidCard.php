<?php

namespace App\Models;

use Database\Factories\RfidCardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $rfid_number
 * @property int|null $student_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['rfid_number', 'student_id'])]
class RfidCard extends Model
{
    /** @use HasFactory<RfidCardFactory> */
    use HasFactory;

    /**
     * Current owner; null means the card is spare (spec 02 §7).
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
