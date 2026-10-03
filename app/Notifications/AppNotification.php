<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    use Queueable;

    public function __construct(
        public NotificationType|string $type,
        public string $title,
        public string $body,
        public string $url,
        public ?string $key = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{key: string|null, type: string, title: string, body: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type instanceof NotificationType ? $this->type->value : (string) $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
