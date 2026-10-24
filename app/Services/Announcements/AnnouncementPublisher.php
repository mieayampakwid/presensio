<?php

namespace App\Services\Announcements;

use App\Enums\NotificationType;
use App\Models\Announcement;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Recipient;
use Illuminate\Support\Facades\DB;

class AnnouncementPublisher
{
    public function __construct(private readonly AnnouncementAudience $audience, private readonly NotificationDispatcher $dispatcher) {}

    public function publishDue(Announcement $item): void
    {
        $record = DB::transaction(function () use ($item): ?Announcement {
            $record = Announcement::whereKey($item->id)->lockForUpdate()->first();
            if ($record === null || ! $record->isLive() || $record->notifications_dispatched_at !== null) {
                return null;
            }
            if ($record->notification_recipients === null) {
                $users = $this->audience->recipientUsers($record)->pluck('id')->map(fn (int $id) => ['type' => 'user', 'id' => $id]);
                $guardians = $this->audience->accountlessGuardians($record)->pluck('id')->map(fn (int $id) => ['type' => 'guardian', 'id' => $id]);
                $record->update(['notification_recipients' => $users->merge($guardians)->values()->all()]);
            }

            return $record;
        });
        if ($record === null) {
            return;
        }
        $types = [];
        if ($record->notify_urgent) {
            $types[] = NotificationType::AnnouncementUrgent;
        }
        if ($record->requires_acknowledgement) {
            $types[] = NotificationType::AnnouncementAckRequired;
        }
        foreach ($record->notification_recipients ?? [] as $identity) {
            if (! Announcement::whereKey($record->id)->exists()) {
                return;
            }
            if ($identity['type'] === 'user') {
                $user = $this->audience->recipientUsers($record)->whereKey($identity['id'])->first();
                if ($user === null) {
                    continue;
                }
                $recipient = Recipient::fromUser($user);
            } else {
                $guardian = $this->audience->accountlessGuardians($record)->whereKey($identity['id'])->first();
                if ($guardian === null) {
                    continue;
                }
                $recipient = Recipient::fromGuardian($guardian);
            }
            foreach ($types as $type) {
                $this->dispatcher->dispatch($type, collect([$recipient]), new Message(title: 'Pengumuman sekolah', body: 'Ada pengumuman sekolah baru. Buka aplikasi untuk membaca.', route: 'announcements.show', routeParameters: ['announcement' => $record->id]), "announcement:{$record->id}");
            }
        }
        Announcement::whereKey($record->id)->update(['notifications_dispatched_at' => now()]);
    }
}
