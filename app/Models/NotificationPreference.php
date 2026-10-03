<?php

namespace App\Models;

use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Opt-out preferences for a user with a guardian profile (spec 17 §Schema 3).
 *
 * @property int $user_id
 * @property string $type_key
 * @property bool $whatsapp_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'type_key',
    'whatsapp_enabled',
])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $primaryKey = ['user_id', 'type_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
