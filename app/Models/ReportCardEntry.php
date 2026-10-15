<?php

namespace App\Models;

use App\Enums\PromotionDecision;
use Database\Factories\ReportCardEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Editable homeroom inputs (notes, promotion decision) before publication.
 *
 * @property int $id
 * @property int $semester_id
 * @property int $student_id
 * @property string|null $homeroom_notes
 * @property string|null $extracurricular_notes
 * @property PromotionDecision|null $promotion_decision
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Semester $semester
 * @property-read Student $student
 */
#[Fillable(['semester_id', 'student_id', 'homeroom_notes', 'extracurricular_notes', 'promotion_decision'])]
class ReportCardEntry extends Model
{
    /** @use HasFactory<ReportCardEntryFactory> */
    use HasFactory;

    /**
     * The semester these entries belong to.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * The student these entries describe.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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
            'student_id' => 'integer',
            'promotion_decision' => PromotionDecision::class,
        ];
    }
}
