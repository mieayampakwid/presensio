<?php

namespace App\Models;

use App\Enums\PeriodKind;
use Database\Factories\LessonPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One ordered period ("Jam ke-n" or a break) within a bell schedule.
 *
 * @property int $id
 * @property int $bell_schedule_id
 * @property PeriodKind $kind
 * @property int|null $number
 * @property string|null $name
 * @property string $start_time
 * @property string $end_time
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BellSchedule $bellSchedule
 */
#[Fillable(['bell_schedule_id', 'kind', 'number', 'name', 'start_time', 'end_time', 'sort_order'])]
class LessonPeriod extends Model
{
    /** @use HasFactory<LessonPeriodFactory> */
    use HasFactory;

    /**
     * The bell schedule this period belongs to.
     *
     * @return BelongsTo<BellSchedule, $this>
     */
    public function bellSchedule(): BelongsTo
    {
        return $this->belongsTo(BellSchedule::class);
    }

    /**
     * Slots anchored at this period (spec 13).
     *
     * @return HasMany<TimetableSlot, $this>
     */
    public function timetableSlots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    /**
     * Whether timetable slots reference this period (spec 13). Slots block deletion.
     */
    public function isInUse(): bool
    {
        return $this->timetableSlots()->exists();
    }

    /**
     * Attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bell_schedule_id' => 'integer',
            'kind' => PeriodKind::class,
            'number' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
