<?php

namespace App\Models;

use Database\Factories\AcademicYearFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An academic year ("2026/2027"). Classes are year-scoped instances of
 * this; exactly one year is active at a time (spec 02 v2.0).
 *
 * @property int $id
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'starts_at', 'ends_at', 'is_active'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    /**
     * The active year — the one all today-surfaces resolve against.
     */
    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->first();
    }

    /**
     * Classes instantiated in this year.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * Scope to the currently active year.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }
}
