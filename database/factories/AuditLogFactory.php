<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => 'updated',
            'auditable_type' => User::class,
            'auditable_id' => 1,
            'old_values' => ['name' => 'Old'],
            'new_values' => ['name' => 'New'],
            'reason' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ];
    }
}
