<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $header_fingerprint
 * @property array<string, mixed> $mapping
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['header_fingerprint', 'mapping'])]
class StudentImportMapping extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
        ];
    }
}
