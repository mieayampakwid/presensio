<?php

namespace App\Jobs;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Mail\NotificationMail;
use App\Models\DatabaseNotification;
use App\Models\Guardian;
use App\Models\NotificationDelivery;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Notifications\WhatsAppClient;
use App\Services\Notifications\WhatsAppQuota;
use App\Services\SchoolSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public int $deliveryId,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status !== DeliveryStatus::Pending) {
            return;
        }

        if ($delivery->scheduled_for !== null && $delivery->scheduled_for->isFuture()) {
            return;
        }

        if ($this->title === '') {
            $notification = DatabaseNotification::query()
                ->where('dedupe_key', $delivery->dedupe_key)
                ->first();
            if ($notification !== null) {
                $this->title = (string) ($notification->data['title'] ?? '');
                $this->body = (string) ($notification->data['body'] ?? '');
                $this->url = $notification->data['url'] ?? null;
            }
        }

        if ($delivery->channel === 'whatsapp') {
            $this->deliverWhatsApp($delivery);
        } elseif ($delivery->channel === 'email') {
            $this->deliverEmail($delivery);
        }
    }

    protected function deliverWhatsApp(NotificationDelivery $delivery): void
    {
        $type = NotificationType::tryFrom($delivery->type_key);
        $quota = app(WhatsAppQuota::class);

        if ($type !== null && $quota->isExceeded($type)) {
            $delivery->update(['status' => DeliveryStatus::SkippedQuota]);

            return;
        }

        $delivery->increment('attempts');

        $schoolSettings = app(SchoolSettings::class);
        $schoolName = $schoolSettings->schoolName();
        $fullUrl = $this->url ? (str_starts_with($this->url, 'http') ? $this->url : url($this->url)) : null;

        $text = "{$this->title}\n\n{$this->body}";
        if ($fullUrl !== null) {
            $text .= "\n\nBuka di aplikasi: {$fullUrl}";
        }
        $text .= "\n\n— Pengelola {$schoolName}";

        try {
            $whatsAppClient = app(WhatsAppClient::class);
            $msgId = $whatsAppClient->send((string) $delivery->recipient_contact, $text);

            $delivery->update([
                'status' => DeliveryStatus::Sent,
                'provider_message_id' => $msgId,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $delivery->update(['error_message' => $e->getMessage()]);

            if ($delivery->attempts >= $this->tries) {
                $delivery->update(['status' => DeliveryStatus::Failed]);
                $this->handleEmailFallback($delivery, $type);
            } else {
                throw $e;
            }
        }
    }

    protected function deliverEmail(NotificationDelivery $delivery): void
    {
        $delivery->increment('attempts');

        try {
            Mail::to((string) $delivery->recipient_contact)->send(
                new NotificationMail($this->title, $this->body, $this->url)
            );

            $delivery->update([
                'status' => DeliveryStatus::Sent,
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $e) {
            $delivery->update(['error_message' => $e->getMessage()]);

            if ($delivery->attempts >= $this->tries) {
                $delivery->update(['status' => DeliveryStatus::Failed]);
            } else {
                throw $e;
            }
        }
    }

    protected function handleEmailFallback(NotificationDelivery $delivery, ?NotificationType $type): void
    {
        if ($type === null) {
            return;
        }

        $schoolSettings = app(SchoolSettings::class);
        $channels = $schoolSettings->notificationChannels();
        $emailEnabled = (bool) ($channels[$type->value]['email'] ?? false);

        if (! $emailEnabled) {
            return;
        }

        $email = null;
        if ($delivery->recipient_type === 'user') {
            $email = User::find($delivery->recipient_id)?->email;
        } elseif ($delivery->recipient_type === 'guardian') {
            $guardian = Guardian::with('user')->find($delivery->recipient_id);
            $email = $guardian?->user?->email;
        } elseif ($delivery->recipient_type === 'employee') {
            $employee = Teacher::with('user')->find($delivery->recipient_id);
            $email = $employee?->user?->email;
        }

        if ($email === null || trim($email) === '') {
            return;
        }

        $emailDelivery = NotificationDelivery::firstOrCreate(
            [
                'dedupe_key' => $delivery->dedupe_key,
                'channel' => 'email',
            ],
            [
                'type_key' => $delivery->type_key,
                'recipient_type' => $delivery->recipient_type,
                'recipient_id' => $delivery->recipient_id,
                'recipient_contact' => $email,
                'status' => DeliveryStatus::Pending,
            ]
        );

        if ($emailDelivery->wasRecentlyCreated || $emailDelivery->status === DeliveryStatus::Pending) {
            DeliverNotification::dispatch($emailDelivery->id, $this->title, $this->body, $this->url);
        }
    }
}
