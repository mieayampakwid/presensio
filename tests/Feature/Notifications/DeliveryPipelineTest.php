<?php

namespace Tests\Feature\Notifications;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Jobs\DeliverNotification;
use App\Mail\NotificationMail;
use App\Models\Guardian;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Notifications\Message;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Recipient;
use App\Services\SchoolSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private NotificationDispatcher $dispatcher;

    private SchoolSettings $schoolSettings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(NotificationDispatcher::class);
        $this->schoolSettings = app(SchoolSettings::class);

        config([
            'services.waha.base_url' => 'https://waha.test',
            'queue.default' => 'sync',
        ]);
    }

    public function test_ac_17_03_guardian_opt_out_skips_whatsapp_but_writes_in_app(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $guardian = Guardian::factory()->create(['user_id' => $user->id, 'phone_number' => '08123456789']);

        // Opt out of report_card_published
        NotificationPreference::create([
            'user_id' => $user->id,
            'type_key' => NotificationType::ReportCardPublished->value,
            'whatsapp_enabled' => false,
        ]);

        $recipient = Recipient::fromGuardian($guardian);
        $message = new Message('Rapor Terbit', 'Rapor telah terbit.', 'dashboard');

        $this->dispatcher->dispatch(
            type: NotificationType::ReportCardPublished,
            recipients: collect([$recipient]),
            message: $message,
            dedupeBase: 'pub-opt-out'
        );

        // In-app was written
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'dedupe_key' => 'report_card_published:pub-opt-out:guardian:'.$guardian->id,
        ]);

        // WhatsApp delivery marked skipped_opt_out
        $this->assertDatabaseHas('notification_deliveries', [
            'type_key' => NotificationType::ReportCardPublished->value,
            'channel' => 'whatsapp',
            'status' => DeliveryStatus::SkippedOptOut->value,
        ]);

        Http::assertNothingSent();
    }

    public function test_ac_17_04_quota_skips_after_limit_but_payment_rejected_is_sent(): void
    {
        Http::fake([
            'https://waha.test/*' => Http::response(['id' => 'msg-123'], 200),
        ]);

        // Set quota to 2
        $this->schoolSettings->row()->update([
            'whatsapp_daily_quota' => 2,
        ]);
        $this->schoolSettings->refresh();

        $guardian = Guardian::factory()->create(['phone_number' => '08123456789']);
        $recipient = Recipient::fromGuardian($guardian);
        $message = new Message('Rapor', 'Rapor terbit.', 'dashboard');

        // First send (quota-bound) -> sent
        $this->dispatcher->dispatch(NotificationType::ReportCardPublished, collect([$recipient]), $message, 'batch-1');
        // Second send (quota-bound) -> sent
        $this->dispatcher->dispatch(NotificationType::ReportCardPublished, collect([$recipient]), $message, 'batch-2');
        // Third send (quota-bound) -> skipped_quota
        $this->dispatcher->dispatch(NotificationType::ReportCardPublished, collect([$recipient]), $message, 'batch-3');

        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'report_card_published:batch-1:guardian:'.$guardian->id,
            'status' => DeliveryStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'report_card_published:batch-2:guardian:'.$guardian->id,
            'status' => DeliveryStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'report_card_published:batch-3:guardian:'.$guardian->id,
            'status' => DeliveryStatus::SkippedQuota->value,
        ]);

        // Payment rejected (not quota-bound) -> sent immediately despite quota
        $paymentMsg = new Message('Pembayaran Ditolak', 'Bukti tidak valid.', 'dashboard');
        $this->dispatcher->dispatch(NotificationType::PaymentRejected, collect([$recipient]), $paymentMsg, 'pay-1');

        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'payment_rejected:pay-1:guardian:'.$guardian->id,
            'status' => DeliveryStatus::Sent->value,
        ]);
    }

    public function test_ac_17_05_quiet_hours_delays_quota_bound_but_not_critical(): void
    {
        // Freeze time at 22:00 WIB
        Date::setTestNow(Date::parse('2026-10-03 22:00:00', 'Asia/Jakarta'));

        // Enable whatsapp for bill_due_reminder in settings
        $channels = $this->schoolSettings->notificationChannels();
        $channels['bill_due_reminder']['whatsapp'] = true;
        $this->schoolSettings->row()->update(['notification_channels' => $channels]);
        $this->schoolSettings->refresh();

        $guardian = Guardian::factory()->create(['phone_number' => '08123456789']);
        $recipient = Recipient::fromGuardian($guardian);

        $billMsg = new Message('Tagihan', 'Pengingat tagihan.', 'dashboard');
        $this->dispatcher->dispatch(NotificationType::BillDueReminder, collect([$recipient]), $billMsg, 'bill-1');

        // bill_due_reminder is delayed to next day 06:00
        $delivery = NotificationDelivery::where('dedupe_key', 'bill_due_reminder:bill-1:guardian:'.$guardian->id)->first();
        $this->assertNotNull($delivery);
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertNotNull($delivery->scheduled_for);
        $this->assertSame(
            Date::parse('2026-10-04 06:00:00', 'Asia/Jakarta')->toDateTimeString(),
            $delivery->scheduled_for->setTimezone('Asia/Jakarta')->toDateTimeString()
        );

        // payment_rejected at 22:00 is sent immediately (not quiet-hours bound)
        Http::fake(['https://waha.test/*' => Http::response(['id' => 'msg-pay'], 200)]);
        $payMsg = new Message('Ditolak', 'Ditolak.', 'dashboard');
        $this->dispatcher->dispatch(NotificationType::PaymentRejected, collect([$recipient]), $payMsg, 'pay-2');

        $payDelivery = NotificationDelivery::where('dedupe_key', 'payment_rejected:pay-2:guardian:'.$guardian->id)->first();
        $this->assertNotNull($payDelivery);
        $this->assertSame(DeliveryStatus::Sent, $payDelivery->status);
        $this->assertNull($payDelivery->scheduled_for);
    }

    public function test_ac_17_07_wa_failure_falls_back_to_email_when_enabled(): void
    {
        Http::fake([
            'https://waha.test/*' => Http::response([], 500),
        ]);
        Mail::fake();

        // Enable email fallback for payment_rejected in settings
        $channels = $this->schoolSettings->notificationChannels();
        $channels['payment_rejected']['email'] = true;
        $this->schoolSettings->row()->update(['notification_channels' => $channels]);
        $this->schoolSettings->refresh();

        $user = User::factory()->create(['email' => 'guardian@example.com']);
        $guardian = Guardian::factory()->create(['user_id' => $user->id, 'phone_number' => '08123456789']);
        $recipient = Recipient::fromGuardian($guardian);

        $delivery = NotificationDelivery::factory()->create([
            'dedupe_key' => 'payment_rejected:fail-1:guardian:'.$guardian->id,
            'type_key' => NotificationType::PaymentRejected->value,
            'channel' => 'whatsapp',
            'recipient_type' => 'guardian',
            'recipient_id' => $guardian->id,
            'recipient_contact' => $guardian->phone_number,
            'status' => DeliveryStatus::Pending,
            'attempts' => 3, // next attempt is 4th
        ]);

        $job = new DeliverNotification($delivery->id, 'Pembayaran Ditolak', 'Bukti transfer tidak valid.', '/dashboard');
        $job->handle();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->error_message);

        // Email was sent as fallback
        Mail::assertSent(NotificationMail::class, function (NotificationMail $mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Email delivery row recorded
        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'payment_rejected:fail-1:guardian:'.$guardian->id,
            'channel' => 'email',
            'status' => DeliveryStatus::Sent->value,
        ]);
    }

    public function test_ac_17_07_wa_failure_ends_failed_when_email_disabled(): void
    {
        Http::fake([
            'https://waha.test/*' => Http::response([], 500),
        ]);
        Mail::fake();

        // Ensure email is disabled for payment_rejected
        $channels = $this->schoolSettings->notificationChannels();
        $channels['payment_rejected']['email'] = false;
        $this->schoolSettings->row()->update(['notification_channels' => $channels]);
        $this->schoolSettings->refresh();

        $guardian = Guardian::factory()->create(['phone_number' => '08123456789']);
        $delivery = NotificationDelivery::factory()->create([
            'dedupe_key' => 'payment_rejected:fail-2:guardian:'.$guardian->id,
            'type_key' => NotificationType::PaymentRejected->value,
            'channel' => 'whatsapp',
            'recipient_type' => 'guardian',
            'recipient_id' => $guardian->id,
            'recipient_contact' => $guardian->phone_number,
            'status' => DeliveryStatus::Pending,
            'attempts' => 3,
        ]);

        $job = new DeliverNotification($delivery->id, 'Pembayaran Ditolak', 'Bukti tidak valid.', '/dashboard');
        $job->handle();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertNotNull($delivery->error_message);

        Mail::assertNothingSent();
    }

    public function test_guardian_without_phone_is_marked_skipped_no_contact(): void
    {
        $guardian = Guardian::factory()->create(['phone_number' => '']);
        $recipient = Recipient::fromGuardian($guardian);

        $message = new Message('Rapor', 'Rapor terbit.', 'dashboard');
        $this->dispatcher->dispatch(NotificationType::ReportCardPublished, collect([$recipient]), $message, 'no-contact-1');

        $this->assertDatabaseHas('notification_deliveries', [
            'dedupe_key' => 'report_card_published:no-contact-1:guardian:'.$guardian->id,
            'status' => DeliveryStatus::SkippedNoContact->value,
        ]);
    }
}
