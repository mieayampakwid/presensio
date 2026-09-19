<?php

namespace Database\Factories;

use App\Enums\NonSchoolDaySource;
use App\Models\NonSchoolDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NonSchoolDay>
 */
class NonSchoolDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => today()->addDays(fake()->numberBetween(1, 60)),
            'name' => fake()->sentence(2),
            'source' => NonSchoolDaySource::Manual,
        ];
    }

    public function synced(): static
    {
        return $this->state(fn () => ['source' => NonSchoolDaySource::Sync]);
    }
}
