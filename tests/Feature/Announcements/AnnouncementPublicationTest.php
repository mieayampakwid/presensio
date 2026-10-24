<?php

namespace Tests\Feature\Announcements;

use App\Jobs\DeliverNotification;
use App\Models\Announcement;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Announcements\AnnouncementPublisher;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class AnnouncementPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_publication_sends_both_types_once_and_never_adds_later_users(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 03:00:00', 'UTC'));
        Queue::fake();
        Http::preventStrayRequests();
        $user = User::factory()->staff()->create();
        $item = Announcement::factory()->create(['audience_type' => 'role', 'audience_role' => 'staff', 'requires_acknowledgement' => true, 'notify_urgent' => true, 'published_at' => now()->addMinute()]);
        $this->artisan('announcements:publish-due')->assertSuccessful();
        $this->assertNull($item->fresh()->notification_recipients);
        $this->travel(2)->minutes();
        $this->artisan('announcements:publish-due')->assertSuccessful();
        $this->assertCount(2, $user->notifications);
        $this->assertNotNull($item->fresh()->notifications_dispatched_at);
        $later = User::factory()->staff()->create();
        $this->artisan('announcements:publish-due')->assertSuccessful();
        $this->assertSame(2, $user->notifications()->count());
        $this->assertSame(0, $later->notifications()->count());
        foreach ($user->notifications as $notice) {
            $this->assertSame('Ada pengumuman sekolah baru. Buka aplikasi untuk membaca.', $notice->data['body']);
            $this->assertStringContainsString('/announcements/', $notice->data['url']);
        }
        Queue::assertNothingPushed();
    }

    public function test_failure_resumes_captured_recipients_without_duplicate_inapp_messages(): void
    {
        $first = User::factory()->staff()->create();
        $second = User::factory()->staff()->create();
        $item = Announcement::factory()->create(['audience_type' => 'role', 'audience_role' => 'staff', 'requires_acknowledgement' => true]);
        $real = app(NotificationDispatcher::class);
        $calls = 0;
        $mock = \Mockery::mock(NotificationDispatcher::class);
        $mock->shouldReceive('dispatch')->andReturnUsing(function (...$args) use ($real, &$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('simulated failure');
            }$real->dispatch(...$args);
        });
        $this->app->instance(NotificationDispatcher::class, $mock);
        try {
            app(AnnouncementPublisher::class)->publishDue($item);
            $this->fail('Expected failure');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure', $e->getMessage());
        }
        $this->assertNull($item->fresh()->notifications_dispatched_at);
        $this->assertCount(2, $item->fresh()->notification_recipients);
        $later = User::factory()->staff()->create();
        $this->app->instance(NotificationDispatcher::class, $real);
        app(AnnouncementPublisher::class)->publishDue($item);
        $this->assertSame(1, $first->notifications()->count());
        $this->assertSame(1, $second->notifications()->count());
        $this->assertSame(0, $later->notifications()->count());
        $this->assertNotNull($item->fresh()->notifications_dispatched_at);
    }

    public function test_empty_notification_selection_completes_and_accountless_guardian_has_external_only_delivery(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 03:00:00', 'UTC'));
        Queue::fake();
        Http::preventStrayRequests();
        $settings = app(SchoolSettings::class);
        $settings->row()->update(['notification_channels' => ['announcement_urgent' => ['whatsapp' => true, 'email' => false]]]);
        $settings->refresh();
        $guardian = Guardian::factory()->create(['user_id' => null]);
        $item = Announcement::factory()->create(['audience_type' => 'role', 'audience_role' => 'parent', 'notify_urgent' => true]);
        app(AnnouncementPublisher::class)->publishDue($item);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('notification_deliveries', ['recipient_type' => 'guardian', 'recipient_id' => $guardian->id, 'type_key' => 'announcement_urgent']);
        $empty = Announcement::factory()->create();
        app(AnnouncementPublisher::class)->publishDue($empty);
        $this->assertNotNull($empty->fresh()->notifications_dispatched_at);
        Queue::assertPushed(DeliverNotification::class);
    }

    public function test_disabled_recipient_and_manager_inspection_privilege_do_not_expand_publication_audience(): void
    {
        $disabled = User::factory()->staff()->create(['is_active' => false]);
        $principal = User::factory()->principal()->create();
        $item = Announcement::factory()->create(['audience_type' => 'role', 'audience_role' => 'staff', 'requires_acknowledgement' => true]);
        app(AnnouncementPublisher::class)->publishDue($item);
        $this->assertSame([], $item->fresh()->notification_recipients);
        $this->assertSame(0, $principal->notifications()->count());
        $this->assertSame(0, $disabled->notifications()->count());
    }
}
