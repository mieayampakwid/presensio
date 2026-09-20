<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Delivery ledger for guardian absence alerts (spec 05): one row per
 * (attendance record, guardian). The unique pair is the queue-retry
 * idempotency key — a row is claimed (pending) before the send attempt,
 * so at most one notification per guardian per absence ever goes out.
 * Known duplicate window: a send that succeeded but whose status update
 * was lost to a process crash can resend on retry.
 *
 * @property int $id
 * @property int $attendance_id
 * @property int $guardian_id
 * @property NotificationChannel $channel
 * @property NotificationDeliveryStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'attendance_id',
    'guardian_id',
    'channel',
    'status',
])]
class AbsenceNotification extends Model
{
    /**
     * The absence record this notification is about.
     *
     * @return BelongsTo<Attendance, $this>
     */
    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Guardian the notification was addressed to.
     *
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => NotificationDeliveryStatus::class,
        ];
    }
}
