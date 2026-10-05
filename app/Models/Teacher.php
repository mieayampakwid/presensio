<?php

namespace App\Models;

use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee|null $employee
 */
#[Fillable(['employee_id'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use HasFactory;

    /**
     * The employee profile for this teacher.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Classes this teacher homerooms.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /**
     * Teaching assignments for this teacher across classes.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class, 'teacher_id');
    }

    /**
     * Scope a query to only active teachers.
     *
     * @param  Builder<Teacher>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereHas('employee', function (Builder $query) {
            $query->where('is_active', true)
                ->where(function (Builder $q) {
                    $q->whereNull('user_id')
                        ->orWhereHas('user', fn (Builder $uq) => $uq->where('is_active', true));
                });
        });
    }

    /**
     * Check if the teacher profile is active.
     */
    public function isActive(): bool
    {
        if ($this->employee === null) {
            return true;
        }

        if (! $this->employee->is_active) {
            return false;
        }

        if ($this->employee->user !== null && ! $this->employee->user->is_active) {
            return false;
        }

        return true;
    }
}
