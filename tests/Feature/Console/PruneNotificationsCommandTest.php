<?php

namespace Tests\Feature\Console;

use App\Models\DatabaseNotification;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PruneNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ac_17_11_prune_removes_read_notifications_older_than_180_days(): void
    {
        $user = User::factory()->create();

        // 1. Read notification older than 180 days -> pruned
        $oldRead = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => AppNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Old Read', 'body' => 'B', 'url' => '/dashboard', 'key' => 'old-read'],
            'dedupe_key' => 'old-read',
            'read_at' => now()->subDays(181),
            'created_at' => now()->subDays(185),
            'updated_at' => now()->subDays(181),
        ]);

        // 2. Read notification within 180 days -> kept
        $recentRead = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => AppNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Recent Read', 'body' => 'B', 'url' => '/dashboard', 'key' => 'recent-read'],
            'dedupe_key' => 'recent-read',
            'read_at' => now()->subDays(10),
            'created_at' => now()->subDays(15),
            'updated_at' => now()->subDays(10),
        ]);

        // 3. Unread notification older than 180 days -> kept (unread kept)
        $oldUnread = DatabaseNotification::create([
            'id' => Str::uuid()->toString(),
            'type' => AppNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['title' => 'Old Unread', 'body' => 'B', 'url' => '/dashboard', 'key' => 'old-unread'],
            'dedupe_key' => 'old-unread',
            'read_at' => null,
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        // 4. Delivery record older than 365 days -> pruned
        $oldDelivery = NotificationDelivery::factory()->create([
            'created_at' => now()->subDays(366),
        ]);

        // 5. Delivery record within 365 days -> kept
        $recentDelivery = NotificationDelivery::factory()->create([
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('notifications:prune')->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread->id]);

        $this->assertDatabaseMissing('notification_deliveries', ['id' => $oldDelivery->id]);
        $this->assertDatabaseHas('notification_deliveries', ['id' => $recentDelivery->id]);
    }
}
