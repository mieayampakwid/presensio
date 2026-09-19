<?php

namespace Database\Factories;

use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            // Unique: phone_number is the guardian identity (dedupe key).
            'phone_number' => fake()->unique()->e164PhoneNumber(),
            'work' => fake()->optional()->jobTitle(),
            'address' => fake()->optional()->address(),
        ];
    }
}
