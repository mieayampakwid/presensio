<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/notifications');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_inbox_with_pagination(): void
    {
        $user = User::factory()->create();

        // Create 25 notifications
        for ($i = 1; $i <= 25; $i++) {
            $user->notify(new AppNotification(
                type: NotificationType::AnnouncementUrgent,
                title: "Pengumuman #{$i}",
                body: "Isi pengumuman {$i}",
                url: '/dashboard',
                key: "announcement:page:{$i}:user:{$user->id}",
            ));
        }

        $response = $this->actingAs($user)->get('/notifications');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications.data', 20)
            ->where('notifications.total', 25)
        );
    }

    public function test_inbox_unread_filter(): void
    {
        $user = User::factory()->create();

        // 2 unread, 1 read
        $user->notify(new AppNotification(
            type: NotificationType::AnnouncementUrgent,
            title: 'Unread 1',
            body: 'Body 1',
            url: '/dashboard',
            key: "key-1:{$user->id}",
        ));
        $user->notify(new AppNotification(
            type: NotificationType::AnnouncementUrgent,
            title: 'Unread 2',
            body: 'Body 2',
            url: '/dashboard',
            key: "key-2:{$user->id}",
        ));
        $user->notify(new AppNotification(
            type: NotificationType::AnnouncementUrgent,
            title: 'Read 1',
            body: 'Body 3',
            url: '/dashboard',
            key: "key-3:{$user->id}",
        ));

        // Mark the 3rd read
        $user->notifications()->where('dedupe_key', "key-3:{$user->id}")->first()->markAsRead();

        $response = $this->actingAs($user)->get('/notifications?unread=1');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('notifications.data', 2)
            ->where('unread_only', true)
        );
    }

    public function test_ac_17_09_user_cannot_read_another_users_notification(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $userB->notify(new AppNotification(
            type: NotificationType::PaymentVerified,
            title: 'Pembayaran Diverifikasi',
            body: 'Bukti transfer valid.',
            url: '/my-fees',
            key: "pay-ver:1:user:{$userB->id}",
        ));

        $notificationB = $userB->notifications()->first();
        $this->assertNotNull($notificationB);

        // User A tries to read user B's notification -> 404
        $response = $this->actingAs($userA)->post("/notifications/{$notificationB->id}/read");
        $response->assertNotFound();

        $notificationB->refresh();
        $this->assertNull($notificationB->read_at);
    }

    public function test_user_can_mark_notification_as_read_and_is_redirected(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification(
            type: NotificationType::ExcuseReviewed,
            title: 'Izin Disetujui',
            body: 'Pengajuan izin telah disetujui.',
            url: '/my-excuses',
            key: "excuse-rev:1:user:{$user->id}",
        ));

        $notification = $user->notifications()->first();

        $response = $this->actingAs($user)->post("/notifications/{$notification->id}/read");
        $response->assertRedirect('/my-excuses');

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new AppNotification(
            type: NotificationType::AnnouncementUrgent,
            title: 'Notif 1',
            body: 'B1',
            url: '/dashboard',
            key: "n1:{$user->id}",
        ));
        $user->notify(new AppNotification(
            type: NotificationType::AnnouncementUrgent,
            title: 'Notif 2',
            body: 'B2',
            url: '/dashboard',
            key: "n2:{$user->id}",
        ));

        $this->assertSame(2, $user->unreadNotifications()->count());

        $response = $this->actingAs($user)->post('/notifications/read-all');
        $response->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }
}
