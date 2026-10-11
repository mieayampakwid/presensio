<?php

namespace App\Models;

use App\Services\SchoolSettings;
use Database\Factories\CourseSemesterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Per-course per-semester settings and finalization state.
 *
 * @property int $id
 * @property int $class_subject_id
 * @property int $semester_id
 * @property string $scope_weight
 * @property string $final_weight
 * @property Carbon|null $finalized_at
 * @property int|null $finalized_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClassSubject $classSubject
 * @property-read Semester $semester
 * @property-read User|null $finalizedBy
 */
#[Fillable(['class_subject_id', 'semester_id', 'scope_weight', 'final_weight', 'finalized_at', 'finalized_by_user_id'])]
class CourseSemester extends Model
{
    /** @use HasFactory<CourseSemesterFactory> */
    use HasFactory;

    /**
     * Lazily get or create course semester settings prefilled from school settings.
     */
    public static function forCourse(ClassSubject $classSubject, Semester $semester): static
    {
        /** @var SchoolSettings $settings */
        $settings = app(SchoolSettings::class);

        return static::firstOrCreate(
            [
                'class_subject_id' => $classSubject->id,
                'semester_id' => $semester->id,
            ],
            [
                'scope_weight' => $settings->defaultScopeWeight(),
                'final_weight' => $settings->defaultFinalWeight(),
            ]
        );
    }

    /**
     * Check if course semester is finalized.
     */
    public function isFinalized(): bool
    {
        return $this->finalized_at !== null;
    }

    /**
     * Guard against mutations on finalized courses.
     */
    public function assertOpen(): void
    {
        if ($this->isFinalized()) {
            abort(422, 'Mata pelajaran untuk semester ini sudah difinalisasi.');
        }
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
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * @return HasMany<SubjectResult, $this>
     */
    public function subjectResults(): HasMany
    {
        return $this->hasMany(SubjectResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'class_subject_id' => 'integer',
            'semester_id' => 'integer',
            'scope_weight' => 'decimal:2',
            'final_weight' => 'decimal:2',
            'finalized_at' => 'datetime',
            'finalized_by_user_id' => 'integer',
        ];
    }
}
