<?php

namespace App\Models;

use App\Enums\NonSchoolDaySource;
use Database\Factories\NonSchoolDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One no-school date (national holiday, cuti bersama, semester break, …).
 *
 * @property int $id
 * @property Carbon $date
 * @property string $name
 * @property NonSchoolDaySource $source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'name', 'source'])]
class NonSchoolDay extends Model
{
    /** @use HasFactory<NonSchoolDayFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'source' => NonSchoolDaySource::class,
        ];
    }
}
