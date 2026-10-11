<?php

namespace App\Models;

use Database\Factories\SubjectResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Working record for descriptions; frozen values at finalization.
 *
 * @property int $id
 * @property int $course_semester_id
 * @property int $student_id
 * @property int|null $final_score
 * @property string|null $description
 * @property bool $description_edited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseSemester $courseSemester
 * @property-read Student $student
 */
#[Fillable(['course_semester_id', 'student_id', 'final_score', 'description', 'description_edited'])]
class SubjectResult extends Model
{
    /** @use HasFactory<SubjectResultFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CourseSemester, $this>
     */
    public function courseSemester(): BelongsTo
    {
        return $this->belongsTo(CourseSemester::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_semester_id' => 'integer',
            'student_id' => 'integer',
            'final_score' => 'integer',
            'description_edited' => 'boolean',
        ];
    }
}
