<?php

namespace App\Models;

use Database\Factories\ShiftRosterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-date shift override for one employee; a null shift is an explicit
 * day off (spec 20 §Decisions "Shift assignment").
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Shift|null $shift
 */
#[Fillable(['employee_id', 'date', 'shift_id'])]
class ShiftRoster extends Model
{
    protected $table = 'shift_roster';

    /** @use HasFactory<ShiftRosterFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    /**
     * The employee this roster row belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * The assigned shift, null for an explicit day off.
     *
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
