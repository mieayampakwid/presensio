<?php

namespace App\Services\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Models\NotificationDelivery;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\Date;

class WhatsAppQuota
{
    public function __construct(private readonly SchoolSettings $schoolSettings) {}

    /**
     * Check whether sending a WhatsApp notification of this type would exceed the daily quota.
     */
    public function isExceeded(NotificationType $type): bool
    {
        if (! $type->quotaBound()) {
            return false;
        }

        $quota = $this->schoolSettings->whatsappDailyQuota();
        if ($quota === null) {
            return false;
        }

        return $this->todayUsage() >= $quota;
    }

    /**
     * Today's sent WhatsApp count for quota-bound types.
     */
    public function todayUsage(): int
    {
        $todayDate = $this->schoolSettings->todayDate();
        $tz = $this->schoolSettings->timezone();

        $startUtc = Date::parse($todayDate.' 00:00:00', $tz)->utc();
        $endUtc = Date::parse($todayDate.' 23:59:59', $tz)->utc();

        $quotaBoundTypes = collect(NotificationType::cases())
            ->filter(fn (NotificationType $t) => $t->quotaBound())
            ->map(fn (NotificationType $t) => $t->value)
            ->all();

        return NotificationDelivery::query()
            ->where('channel', 'whatsapp')
            ->where('status', DeliveryStatus::Sent)
            ->whereIn('type_key', $quotaBoundTypes)
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->count();
    }
}
