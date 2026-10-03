<?php

namespace App\Models;

use Database\Factories\ClassSubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A teaching assignment of a subject to a class by a teacher.
 *
 * @property int $id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property string $passing_threshold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SchoolClass $schoolClass
 * @property-read Subject $subject
 * @property-read Teacher|null $teacher
 */
#[Fillable(['class_id', 'subject_id', 'teacher_id', 'passing_threshold'])]
class ClassSubject extends Model
{
    /** @use HasFactory<ClassSubjectFactory> */
    use HasFactory;

    /**
     * The class this assignment belongs to.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * The subject being taught.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The teacher assigned to instruct this subject.
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Check if this class subject assignment has downstream usage
     * (assessments in plan 5, timetable slots in plan 11).
     */
    public function isInUse(): bool
    {
        return false;
    }

    /**
     * Attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'class_id' => 'integer',
            'subject_id' => 'integer',
            'teacher_id' => 'integer',
            'passing_threshold' => 'decimal:2',
        ];
    }
}
