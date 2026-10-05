<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Models\Concerns\Auditable;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $employee_number
 * @property string|null $phone_number
 * @property EmploymentType $employment_type
 * @property string|null $position
 * @property list<int>|null $working_days
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Teacher|null $teacher
 */
#[Fillable([
    'user_id',
    'name',
    'employee_number',
    'phone_number',
    'employment_type',
    'position',
    'working_days',
    'is_active',
])]
class Employee extends Model
{
    use Auditable;

    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'working_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

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
     * The teaching extension profile, if this employee is a teacher.
     *
     * @return HasOne<Teacher, $this>
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * RFID cards assigned to this employee.
     *
     * @return HasMany<RfidCard, $this>
     */
    public function rfidCards(): HasMany
    {
        return $this->hasMany(RfidCard::class);
    }

    /**
     * Scope a query to only active employees.
     *
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Check if this employee is a teacher.
     */
    public function isTeacher(): bool
    {
        if ($this->relationLoaded('teacher')) {
            return $this->teacher !== null;
        }

        return $this->teacher()->exists();
    }
}
