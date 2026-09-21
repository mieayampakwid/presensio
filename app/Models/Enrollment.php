<?php

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class membership over time — the single source of truth for "which
 * class was this student in on date X" (spec 02 v2.0 / spec 07). A
 * student has at most one open enrollment (`ended_on` null), enforced
 * by a partial unique index.
 *
 * @property int $id
 * @property int $student_id
 * @property int $class_id
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['student_id', 'class_id', 'started_on', 'ended_on'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory;

    /**
     * The student this membership belongs to.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The class the student was enrolled in.
     *
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Enrollments covering the given Y-m-d date.
     */
    public function scopeActiveOn(Builder $query, string $date): void
    {
        $query->where('started_on', '<=', $date)
            ->where(fn ($q) => $q->whereNull('ended_on')->orWhere('ended_on', '>=', $date));
    }

    /**
     * Does this enrollment overlap the inclusive Y-m-d range?
     */
    public function overlaps(string $from, string $to): bool
    {
        return $this->started_on->toDateString() <= $to
            && ($this->ended_on === null || $this->ended_on->toDateString() >= $from);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_on' => 'date:Y-m-d',
            'ended_on' => 'date:Y-m-d',
        ];
    }
}
