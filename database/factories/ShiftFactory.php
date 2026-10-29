<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'start_time' => '07:00',
            'end_time' => '14:00',
            'crosses_midnight' => false,
            'sweep_offset_minutes' => 120,
        ];
    }

    /**
     * Indicate that the shift crosses midnight (19:00 - 07:00).
     */
    public function night(): static
    {
        return $this->state(fn (): array => [
            'name' => 'Shift Malam',
            'start_time' => '19:00',
            'end_time' => '07:00',
            'crosses_midnight' => true,
        ]);
    }
}
