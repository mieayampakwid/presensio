<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
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
            // Postgres unique constraint violation error code
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
        // External channels (WhatsApp, email, quota, quiet hours) wired in Task 3.
    }
}
