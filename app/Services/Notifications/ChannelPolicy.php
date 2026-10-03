<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Services\SchoolSettings;

class ChannelPolicy
{
    public function __construct(private readonly SchoolSettings $schoolSettings) {}

    /**
     * Whether the channel is enabled in school settings for this notification type.
     */
    public function isChannelEnabled(NotificationType $type, string $channel): bool
    {
        $channels = $this->schoolSettings->notificationChannels();

        return (bool) ($channels[$type->value][$channel] ?? false);
    }

    /**
     * Whether the recipient has opted out of WhatsApp notifications for this type.
     */
    public function isOptedOut(NotificationType $type, Recipient $recipient): bool
    {
        if (! $type->optOutAllowed()) {
            return false;
        }

        if ($recipient->user === null) {
            return false;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $recipient->user->id)
            ->where('type_key', $type->value)
            ->first();

        return $preference !== null && ! $preference->whatsapp_enabled;
    }

    /**
     * Whether the recipient has a valid contact destination for the channel.
     */
    public function hasContact(Recipient $recipient, string $channel): bool
    {
        return match ($channel) {
            'whatsapp' => ! empty(trim($recipient->phone ?? '')),
            'email' => ! empty(trim($recipient->email ?? '')),
            default => false,
        };
    }

    public function getContact(Recipient $recipient, string $channel): ?string
    {
        return match ($channel) {
            'whatsapp' => trim($recipient->phone ?? '') ?: null,
            'email' => trim($recipient->email ?? '') ?: null,
            default => null,
        };
    }
}
