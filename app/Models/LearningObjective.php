<?php

namespace App\Models;

use Database\Factories\LearningObjectiveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A learning objective (Tujuan Pembelajaran / TP) scoped to (academic_year_id, subject_id, grade_level).
 *
 * @property int $id
 * @property int $academic_year_id
 * @property int $subject_id
 * @property int $grade_level
 * @property string $code
 * @property string $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AcademicYear $academicYear
 * @property-read Subject $subject
 */
#[Fillable(['academic_year_id', 'subject_id', 'grade_level', 'code', 'description', 'sort_order'])]
class LearningObjective extends Model
{
    /** @use HasFactory<LearningObjectiveFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsToMany<Assessment, $this>
     */
    public function assessments(): BelongsToMany
    {
        return $this->belongsToMany(Assessment::class, 'assessment_objectives');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'academic_year_id' => 'integer',
            'subject_id' => 'integer',
            'grade_level' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
