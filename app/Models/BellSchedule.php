<?php

namespace App\Models;

use Database\Factories\BellScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A school bell schedule (e.g. "Reguler", "Jumat") applying to a set of ISO weekdays.
 *
 * @property int $id
 * @property string $name
 * @property list<int> $weekdays
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LessonPeriod> $periods
 */
#[Fillable(['name', 'weekdays'])]
class BellSchedule extends Model
{
    /** @use HasFactory<BellScheduleFactory> */
    use HasFactory;

    /**
     * Ordered lesson periods of this schedule.
     *
     * @return HasMany<LessonPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(LessonPeriod::class)->orderBy('sort_order');
    }

    /**
     * Attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
        ];
    }
}
