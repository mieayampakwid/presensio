<?php

namespace Tests\Feature\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Models\Guardian;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private SchoolSettings $schoolSettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schoolSettings = app(SchoolSettings::class);
    }

    public function test_admin_can_view_notification_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/settings/notifications');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/notifications')
            ->has('channels')
            ->has('catalog')
            ->has('whatsapp_daily_quota')
            ->has('today_whatsapp_usage')
            ->has('quiet_hours_start')
            ->has('quiet_hours_end')
            ->has('bill_reminder_days_before')
        );
    }

    public function test_non_admin_cannot_access_notification_settings(): void
    {
        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($teacher)->get('/settings/notifications');
        $response->assertForbidden();
    }

    public function test_admin_can_update_notification_settings_with_catalog_clamping(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = [
            'notification_channels' => [
                // Allowed
                'report_card_published' => ['whatsapp' => true, 'email' => true],
                // Not allowed in catalog (must clamp)
                'absence_alert' => ['whatsapp' => true, 'email' => true],
            ],
            'whatsapp_daily_quota' => 200,
            'quiet_hours_start' => '22:00',
            'quiet_hours_end' => '05:00',
            'bill_reminder_days_before' => 5,
        ];

        $response = $this->actingAs($admin)->put('/settings/notifications', $payload);
        $response->assertRedirect('/settings/notifications');

        $this->schoolSettings->refresh();

        $this->assertSame(200, $this->schoolSettings->whatsappDailyQuota());
        $this->assertSame(5, $this->schoolSettings->billReminderDaysBefore());
        $this->assertSame(['start' => '22:00:00', 'end' => '05:00:00'], $this->schoolSettings->quietHours());

        $channels = $this->schoolSettings->notificationChannels();
        $this->assertTrue($channels['report_card_published']['whatsapp']);
        $this->assertTrue($channels['report_card_published']['email']);
        // Clamped to false
        $this->assertFalse($channels['absence_alert']['whatsapp']);
        $this->assertFalse($channels['absence_alert']['email']);
    }

    public function test_admin_and_principal_can_view_delivery_log(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();
        $teacher = User::factory()->teacher()->create();

        NotificationDelivery::factory()->create([
            'type_key' => NotificationType::ReportCardPublished->value,
            'channel' => 'whatsapp',
            'status' => DeliveryStatus::Sent,
        ]);

        $adminRes = $this->actingAs($admin)->get('/notification-deliveries');
        $adminRes->assertOk();
        $adminRes->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/deliveries')
            ->has('deliveries.data', 1)
        );

        $principalRes = $this->actingAs($principal)->get('/notification-deliveries');
        $principalRes->assertOk();

        $teacherRes = $this->actingAs($teacher)->get('/notification-deliveries');
        $teacherRes->assertForbidden();
    }

    public function test_delivery_log_can_be_filtered(): void
    {
        $admin = User::factory()->admin()->create();

        NotificationDelivery::factory()->create([
            'type_key' => NotificationType::PaymentRejected->value,
            'channel' => 'whatsapp',
            'status' => DeliveryStatus::Sent,
        ]);

        NotificationDelivery::factory()->create([
            'type_key' => NotificationType::ReportCardPublished->value,
            'channel' => 'whatsapp',
            'status' => DeliveryStatus::SkippedQuota,
        ]);

        $response = $this->actingAs($admin)->get('/notification-deliveries?type=payment_rejected&status=sent');
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/deliveries')
            ->has('deliveries.data', 1)
            ->where('deliveries.data.0.type_key', NotificationType::PaymentRejected->value)
        );
    }

    public function test_guardian_can_update_opt_out_preferences(): void
    {
        $user = User::factory()->parent()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id]);

        $payload = [
            'preferences' => [
                'report_card_published' => false,
                'excuse_reviewed' => true,
            ],
        ];

        $response = $this->actingAs($user)->put('/settings/notification-preferences', $payload);
        $response->assertRedirect('/settings/profile');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type_key' => 'report_card_published',
            'whatsapp_enabled' => false,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type_key' => 'excuse_reviewed',
            'whatsapp_enabled' => true,
        ]);
    }
}
