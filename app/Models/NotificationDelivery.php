<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Ledger row for an external notification attempt (spec 17 §Schema 2).
 *
 * @property int $id
 * @property string $dedupe_key
 * @property string $type_key
 * @property string $channel
 * @property string $recipient_type
 * @property int $recipient_id
 * @property string|null $recipient_contact
 * @property DeliveryStatus $status
 * @property Carbon|null $scheduled_for
 * @property int $attempts
 * @property string|null $provider_message_id
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'dedupe_key',
    'type_key',
    'channel',
    'recipient_type',
    'recipient_id',
    'recipient_contact',
    'status',
    'scheduled_for',
    'attempts',
    'provider_message_id',
    'error_message',
    'sent_at',
])]
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
