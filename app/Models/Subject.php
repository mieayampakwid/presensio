<?php

namespace App\Models;

use App\Enums\SubjectGroup;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An academic subject in the school-wide catalog.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property SubjectGroup $group
 * @property int $sort_order
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'group', 'sort_order', 'description', 'is_active'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Teaching assignments for this subject across classes.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * Attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => SubjectGroup::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
