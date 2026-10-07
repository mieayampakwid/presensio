<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Monthly sequence counter for journal entry numbers (spec 14 §Journals).
 *
 * @property string $period
 * @property int $last_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'period',
    'last_number',
])]
class JournalSequence extends Model
{
    protected $primaryKey = 'period';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_number' => 'integer',
        ];
    }
}
