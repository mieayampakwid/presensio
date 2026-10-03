<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationType;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dedupe_key' => Str::random(24),
            'type_key' => NotificationType::ReportCardPublished->value,
            'channel' => 'whatsapp',
            'recipient_type' => 'user',
            'recipient_id' => User::factory(),
            'recipient_contact' => fake()->phoneNumber(),
            'status' => DeliveryStatus::Pending,
            'scheduled_for' => null,
            'attempts' => 0,
            'provider_message_id' => null,
            'error_message' => null,
            'sent_at' => null,
        ];
    }
}
