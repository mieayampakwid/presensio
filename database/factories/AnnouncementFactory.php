<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Announcement> */
class AnnouncementFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return ['title' => fake()->sentence(), 'body' => fake()->paragraph(), 'audience_type' => 'all', 'published_at' => now()->subMinute(), 'created_by_user_id' => User::factory()->admin()];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['published_at' => now()->addHour()]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }
}
