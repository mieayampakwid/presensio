<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => fake()->unique()->date('Y-m'),
            'status' => 'open',
            'closed_at' => null,
            'closed_by_user_id' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }
}
