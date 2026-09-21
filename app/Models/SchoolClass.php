<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academic_year_id
 * @property string $name
 * @property int|null $teacher_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['academic_year_id', 'name', 'teacher_id'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory;

    /**
     * The table associated with the model (`class` is a reserved word, so the
     * conventional pluralization guess `school_classes` does not apply).
     */
    protected $table = 'classes';

    /**
     * The academic year this class instance belongs to — "5A 2026/2027"
     * and "5A 2027/2028" are different rows (spec 02 v2.0).
     *
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Homeroom teacher. Links to `teachers`, not `users` (spec 02 §13).
     *
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Full enrollment history — including past and ended memberships
     * (deletion guard reads this).
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /**
     * Students currently enrolled (open enrollment only) — today-rosters
     * for boards and pickers; historical attribution goes through
     * enrollments instead (spec 07).
     *
     * @return HasManyThrough<Student, Enrollment, $this>
     */
    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            Enrollment::class,
            'class_id',
            'id',
            'id',
            'student_id',
        )->whereNull('enrollments.ended_on');
    }
}
