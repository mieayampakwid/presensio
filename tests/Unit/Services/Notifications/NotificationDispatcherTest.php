<?php

namespace Tests\Unit\Services\Notifications;

use App\Enums\NotificationType;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Recipient;
use App\Services\Notifications\RecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private NotificationDispatcher $dispatcher;

    private RecipientResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(NotificationDispatcher::class);
        $this->resolver = app(RecipientResolver::class);
    }

    public function test_dispatches_in_app_notification_to_user(): void
    {
        $user = User::factory()->create();
        $recipient = Recipient::fromUser($user);

        $message = new Message(
            title: 'Pengumuman Penting',
            body: 'Ada pengumuman baru untuk Anda.',
            route: 'dashboard'
        );

        $this->dispatcher->dispatch(
            type: NotificationType::AnnouncementUrgent,
            recipients: collect([$recipient]),
            message: $message,
            dedupeBase: 'announcement-101'
        );

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'dedupe_key' => 'announcement_urgent:announcement-101:user:'.$user->id,
        ]);

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Pengumuman Penting', $notification->data['title']);
        $this->assertSame('Ada pengumuman baru untuk Anda.', $notification->data['body']);
        $this->assertStringContainsString('dashboard', $notification->data['url']);
    }

    public function test_second_dispatch_is_no_op_via_dedupe_key(): void
    {
        $user = User::factory()->create();
        $recipient = Recipient::fromUser($user);

        $message = new Message(
            title: 'Rapor Terbit',
            body: 'Rapor semester 1 telah terbit.',
            route: 'dashboard'
        );

        $this->dispatcher->dispatch(
            type: NotificationType::ReportCardPublished,
            recipients: collect([$recipient]),
            message: $message,
            dedupeBase: 'pub-5a-sem1'
        );

        $this->assertSame(1, $user->notifications()->count());

        // Re-run dispatch with same type, dedupeBase, and recipient
        $this->dispatcher->dispatch(
            type: NotificationType::ReportCardPublished,
            recipients: collect([$recipient]),
            message: $message,
            dedupeBase: 'pub-5a-sem1'
        );

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_rolled_back_transaction_never_notifies(): void
    {
        $user = User::factory()->create();
        $recipient = Recipient::fromUser($user);

        $message = new Message(
            title: 'Pembayaran Ditolak',
            body: 'Bukti transfer tidak valid.',
            route: 'dashboard'
        );

        try {
            DB::transaction(function () use ($recipient, $message) {
                $this->dispatcher->dispatch(
                    type: NotificationType::PaymentRejected,
                    recipients: collect([$recipient]),
                    message: $message,
                    dedupeBase: 'payment-999'
                );

                throw new \RuntimeException('Transaction failed');
            });
        } catch (\RuntimeException) {
            // caught
        }

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_message_data_never_contains_sensitive_keys(): void
    {
        $user = User::factory()->create();
        $recipient = Recipient::fromUser($user);

        $message = new Message(
            title: 'Keputusan Izin',
            body: 'Izin kehadiran telah disetujui.',
            route: 'dashboard'
        );

        $this->dispatcher->dispatch(
            type: NotificationType::ExcuseReviewed,
            recipients: collect([$recipient]),
            message: $message,
            dedupeBase: 'excuse-12'
        );

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);

        $data = $notification->data;
        $this->assertArrayNotHasKey('reason', $data);
        $this->assertArrayNotHasKey('score', $data);
        $this->assertArrayNotHasKey('attachment', $data);
        $this->assertArrayNotHasKey('attachment_url', $data);
    }

    public function test_recipient_resolver_resolves_user_guardian_and_class(): void
    {
        $guardianUser = User::factory()->create();
        $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);

        $recipientFromGuardian = $this->resolver->forGuardian($guardian);
        $this->assertSame('guardian', $recipientFromGuardian->type);
        $this->assertSame($guardian->id, $recipientFromGuardian->id);
        $this->assertSame($guardianUser->id, $recipientFromGuardian->user?->id);

        $teacherUser = User::factory()->teacher()->create();
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $class = SchoolClass::factory()->create(['teacher_id' => $teacher->id]);

        $homeroomRecipient = $this->resolver->forHomeroomTeacher($class);
        $this->assertNotNull($homeroomRecipient);
        $this->assertSame($teacherUser->id, $homeroomRecipient->user?->id);
    }
}
