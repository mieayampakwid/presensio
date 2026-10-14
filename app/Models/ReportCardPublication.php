<?php

namespace App\Models;

use Database\Factories\ReportCardPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An immutable, versioned publication of a class's report cards for a semester.
 *
 * @property int $id
 * @property int $semester_id
 * @property int $class_id
 * @property int $version
 * @property Carbon $published_at
 * @property int $published_by_user_id
 * @property Carbon|null $retracted_at
 * @property int|null $retracted_by_user_id
 * @property string|null $retract_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Semester $semester
 * @property-read SchoolClass $schoolClass
 * @property-read User $publishedBy
 * @property-read User|null $retractedBy
 * @property-read Collection<int, ReportCard> $reportCards
 */
#[Fillable(['semester_id', 'class_id', 'version', 'published_at', 'published_by_user_id', 'retracted_at', 'retracted_by_user_id', 'retract_reason'])]
class ReportCardPublication extends Model
{
    /** @use HasFactory<ReportCardPublicationFactory> */
    use HasFactory;

    /**
     * Scope to the publication currently visible to guardians and students
     * (the latest version that has not been retracted).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('retracted_at');
    }

    /**
     * The semester this publication belongs to.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * The class whose report cards were published.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * The user who published this version.
     *
     * @return BelongsTo<User, $this>
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * The user who retracted this version, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function retractedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retracted_by_user_id');
    }

    /**
     * The per-student snapshot rows of this publication.
     *
     * @return HasMany<ReportCard, $this>
     */
    public function reportCards(): HasMany
    {
        return $this->hasMany(ReportCard::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester_id' => 'integer',
            'class_id' => 'integer',
            'version' => 'integer',
            'published_at' => 'datetime',
            'retracted_at' => 'datetime',
        ];
    }
}
