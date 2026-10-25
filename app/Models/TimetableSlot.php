<?php

namespace App\Models;

use Database\Factories\TimetableSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A weekly timetable entry: a class slot bound to a starting lesson period
 * with a span, holding either a class subject or an activity (spec 13).
 *
 * @property int $id
 * @property int $semester_id
 * @property int $class_id
 * @property int $day_of_week
 * @property int $lesson_period_id
 * @property int $period_count
 * @property int|null $class_subject_id
 * @property string|null $activity_name
 * @property string|null $room
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Semester $semester
 * @property-read SchoolClass $schoolClass
 * @property-read LessonPeriod $lessonPeriod
 * @property-read ClassSubject|null $classSubject
 */
#[Fillable(['semester_id', 'class_id', 'day_of_week', 'lesson_period_id', 'period_count', 'class_subject_id', 'activity_name', 'room'])]
class TimetableSlot extends Model
{
    /** @use HasFactory<TimetableSlotFactory> */
    use HasFactory;

    /**
     * The semester this slot is scoped to.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * The class this slot belongs to.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * The starting lesson period; times derive from its bell schedule.
     *
     * @return BelongsTo<LessonPeriod, $this>
     */
    public function lessonPeriod(): BelongsTo
    {
        return $this->belongsTo(LessonPeriod::class);
    }

    /**
     * The subject assignment; null for activity slots (AC-13-05).
     *
     * @return BelongsTo<ClassSubject, $this>
     */
    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
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
            'day_of_week' => 'integer',
            'lesson_period_id' => 'integer',
            'period_count' => 'integer',
            'class_subject_id' => 'integer',
        ];
    }
}
