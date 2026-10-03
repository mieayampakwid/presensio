<?php

namespace App\Services\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Jobs\DeliverNotification;
use App\Models\NotificationDelivery;
use App\Notifications\AppNotification;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificationDispatcher
{
    /**
     * Dispatch a notification to the specified recipients.
     *
     * @param  Collection<int, Recipient>  $recipients
     */
    public function dispatch(
        NotificationType $type,
        Collection $recipients,
        Message $message,
        string $dedupeBase,
    ): void {
        $action = function () use ($type, $recipients, $message, $dedupeBase): void {
            $this->executeDispatch($type, $recipients, $message, $dedupeBase);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($action);
        } else {
            $action();
        }
    }

    /**
     * @param  Collection<int, Recipient>  $recipients
     */
    protected function executeDispatch(
        NotificationType $type,
        Collection $recipients,
        Message $message,
        string $dedupeBase,
    ): void {
        foreach ($recipients as $recipient) {
            $dedupeKey = "{$type->value}:{$dedupeBase}:{$recipient->key()}";

            $this->dispatchInApp($type, $recipient, $message, $dedupeKey);
            $this->dispatchExternal($type, $recipient, $message, $dedupeKey);
        }
    }

    protected function dispatchInApp(
        NotificationType $type,
        Recipient $recipient,
        Message $message,
        string $dedupeKey,
    ): void {
        if (! $type->inApp() || $recipient->user === null) {
            return;
        }

        if ($recipient->user->notifications()->where('dedupe_key', $dedupeKey)->exists()) {
            return;
        }

        try {
            $recipient->user->notify(new AppNotification(
                type: $type,
                title: $message->title,
                body: $message->body,
                url: $message->url,
                key: $dedupeKey,
            ));
        } catch (UniqueConstraintViolationException) {
            // Already dispatched; second dispatch is an idempotent no-op (AC-17-02)
        } catch (QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }
        }
    }

    protected function dispatchExternal(
        NotificationType $type,
        Recipient $recipient,
        Message $message,
        string $dedupeKey,
    ): void {
        $policy = app(ChannelPolicy::class);
        $quietHours = app(QuietHours::class);
        $quota = app(WhatsAppQuota::class);

        // 1. WhatsApp Channel
        if ($policy->isChannelEnabled($type, 'whatsapp')) {
            $this->dispatchWhatsAppDelivery($type, $recipient, $message, $dedupeKey, $policy, $quietHours, $quota);
        }

        // 2. Direct Email Channel
        if ($policy->isChannelEnabled($type, 'email')) {
            $this->dispatchEmailDelivery($type, $recipient, $message, $dedupeKey, $policy);
        }
    }

    protected function dispatchWhatsAppDelivery(
        NotificationType $type,
        Recipient $recipient,
        Message $message,
        string $dedupeKey,
        ChannelPolicy $policy,
        QuietHours $quietHours,
        WhatsAppQuota $quota,
    ): void {
        $existing = NotificationDelivery::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('channel', 'whatsapp')
            ->first();

        if ($existing !== null) {
            return;
        }

        if ($policy->isOptedOut($type, $recipient)) {
            NotificationDelivery::create([
                'dedupe_key' => $dedupeKey,
                'type_key' => $type->value,
                'channel' => 'whatsapp',
                'recipient_type' => $recipient->type,
                'recipient_id' => $recipient->id,
                'recipient_contact' => $recipient->phone,
                'status' => DeliveryStatus::SkippedOptOut,
            ]);

            return;
        }

        if (! $policy->hasContact($recipient, 'whatsapp')) {
            NotificationDelivery::create([
                'dedupe_key' => $dedupeKey,
                'type_key' => $type->value,
                'channel' => 'whatsapp',
                'recipient_type' => $recipient->type,
                'recipient_id' => $recipient->id,
                'recipient_contact' => null,
                'status' => DeliveryStatus::SkippedNoContact,
            ]);

            return;
        }

        if ($quota->isExceeded($type)) {
            NotificationDelivery::create([
                'dedupe_key' => $dedupeKey,
                'type_key' => $type->value,
                'channel' => 'whatsapp',
                'recipient_type' => $recipient->type,
                'recipient_id' => $recipient->id,
                'recipient_contact' => $recipient->phone,
                'status' => DeliveryStatus::SkippedQuota,
            ]);

            return;
        }

        $scheduledFor = null;
        if ($type->quietHoursBound() && $quietHours->isQuietTime()) {
            $scheduledFor = $quietHours->delayUntil();
        }

        $delivery = NotificationDelivery::create([
            'dedupe_key' => $dedupeKey,
            'type_key' => $type->value,
            'channel' => 'whatsapp',
            'recipient_type' => $recipient->type,
            'recipient_id' => $recipient->id,
            'recipient_contact' => $recipient->phone,
            'status' => DeliveryStatus::Pending,
            'scheduled_for' => $scheduledFor,
        ]);

        if ($scheduledFor === null) {
            DeliverNotification::dispatch($delivery->id, $message->title, $message->body, $message->url);
        }
    }

    protected function dispatchEmailDelivery(
        NotificationType $type,
        Recipient $recipient,
        Message $message,
        string $dedupeKey,
        ChannelPolicy $policy,
    ): void {
        $existing = NotificationDelivery::query()
            ->where('dedupe_key', $dedupeKey)
            ->where('channel', 'email')
            ->first();

        if ($existing !== null) {
            return;
        }

        if (! $policy->hasContact($recipient, 'email')) {
            NotificationDelivery::create([
                'dedupe_key' => $dedupeKey,
                'type_key' => $type->value,
                'channel' => 'email',
                'recipient_type' => $recipient->type,
                'recipient_id' => $recipient->id,
                'recipient_contact' => null,
                'status' => DeliveryStatus::SkippedNoContact,
            ]);

            return;
        }

        $delivery = NotificationDelivery::create([
            'dedupe_key' => $dedupeKey,
            'type_key' => $type->value,
            'channel' => 'email',
            'recipient_type' => $recipient->type,
            'recipient_id' => $recipient->id,
            'recipient_contact' => $recipient->email,
            'status' => DeliveryStatus::Pending,
        ]);

        DeliverNotification::dispatch($delivery->id, $message->title, $message->body, $message->url);
    }
}
