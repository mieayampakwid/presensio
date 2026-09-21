<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $full_name
 * @property string|null $nickname
 * @property Carbon $dob
 * @property string|null $student_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'full_name', 'nickname', 'dob', 'student_number'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * The login account linked to this profile, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Class membership history — the source of truth for "which class on
     * which date" (spec 02 v2.0 / spec 07).
     *
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * The open enrollment, if any (alumni/left students have none).
     *
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->whereNull('ended_on')->latest('started_on');
    }

    /**
     * The class this student was enrolled in on the given Y-m-d date,
     * derived from enrollment history — never stored on the student.
     */
    public function classOn(string $date): ?SchoolClass
    {
        /** @var Enrollment|null $enrollment */
        $enrollment = $this->enrollments()
            ->where('started_on', '<=', $date)
            ->where(fn ($query) => $query->whereNull('ended_on')->orWhere('ended_on', '>=', $date))
            ->first();

        return $enrollment?->schoolClass;
    }

    /**
     * The class the student is currently enrolled in, derived from the
     * open enrollment (spec 07) — eager-load `currentEnrollment.schoolClass`
     * before reading on collections.
     */
    public function getSchoolClassAttribute(): ?SchoolClass
    {
        return $this->currentEnrollment?->schoolClass;
    }

    /**
     * Derived current class id — keeps form and API shapes stable now
     * that the column is gone.
     */
    public function getClassIdAttribute(): ?int
    {
        return $this->currentEnrollment?->class_id;
    }

    /**
     * Guardians linked to this student.
     *
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class);
    }

    /**
     * RFID cards currently assigned to this student.
     *
     * @return HasMany<RfidCard, $this>
     */
    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class);
    }

    /**
     * Attendance records, one per day (spec 03).
     *
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Guardian-submitted absence excuses covering this student (spec 04).
     *
     * @return HasMany<Excuse, $this>
     */
    public function excuses(): HasMany
    {
        return $this->hasMany(Excuse::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob' => 'date:Y-m-d',
        ];
    }
}
