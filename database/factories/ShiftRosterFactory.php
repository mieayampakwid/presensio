<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftRoster>
 */
class ShiftRosterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->date(),
            'shift_id' => Shift::factory(),
        ];
    }

    /**
     * Indicate that the date is an explicit day off.
     */
    public function dayOff(): static
    {
        return $this->state(fn (): array => [
            'shift_id' => null,
        ]);
    }
}
