<?php

namespace App\Models;

use App\Enums\AssessmentKind;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An assessment within a class subject and semester.
 *
 * @property int $id
 * @property int $class_subject_id
 * @property int $semester_id
 * @property string $name
 * @property AssessmentKind $kind
 * @property Carbon $assessed_on
 * @property string $max_score
 * @property Carbon|null $released_at
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClassSubject $classSubject
 * @property-read Semester $semester
 */
#[Fillable(['class_subject_id', 'semester_id', 'name', 'kind', 'assessed_on', 'max_score', 'released_at', 'sort_order'])]
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    /**
     * Check if this assessment results have been released.
     */
    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsToMany<LearningObjective, $this>
     */
    public function learningObjectives(): BelongsToMany
    {
        return $this->belongsToMany(LearningObjective::class, 'assessment_objectives');
    }

    /**
     * @return HasMany<Score, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'class_subject_id' => 'integer',
            'semester_id' => 'integer',
            'kind' => AssessmentKind::class,
            'assessed_on' => 'date',
            'max_score' => 'decimal:2',
            'released_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }
}
