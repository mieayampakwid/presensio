<?php

namespace App\Models;

use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A semester under an academic year (1 = Ganjil, 2 = Genap).
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $number
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear $academicYear
 */
#[Fillable(['academic_year_id', 'number', 'name', 'starts_at', 'ends_at'])]
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use HasFactory;

    /**
     * The academic year this semester belongs to.
     *
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'number' => 'integer',
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
        ];
    }
}
