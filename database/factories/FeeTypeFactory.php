<?php

namespace Database\Factories;

use App\Enums\FeeKind;
use App\Models\Account;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeType>
 */
class FeeTypeFactory extends Factory
{
    protected $model = FeeType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => null,
            'name' => ucfirst(fake()->unique()->word()),
            'code' => strtoupper(fake()->unique()->bothify('FEE-####')),
            'type' => FeeKind::MonthlyRecurring,
            'default_amount' => '350000.00',
            'description' => null,
            'receivable_account_id' => Account::factory()->asset(),
            'revenue_account_id' => Account::factory()->revenue(),
            'discount_account_id' => Account::factory()->revenue()->contra(),
            'is_active' => true,
        ];
    }

    public function oneTime(): static
    {
        return $this->state(fn () => [
            'type' => FeeKind::OneTime,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
