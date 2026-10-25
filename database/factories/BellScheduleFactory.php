<?php

namespace Database\Factories;

use App\Models\BellSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BellSchedule>
 */
class BellScheduleFactory extends Factory
{
    protected $model = BellSchedule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()),
            'weekdays' => [1, 2, 3, 4, 5],
        ];
    }
}
