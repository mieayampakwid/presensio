<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->randomElement(['1', '2', '3', '4', '5']);
        $number = fake()->unique()->numberBetween(1000, 9999);

        return [
            'code' => "{$prefix}-{$number}",
            'name' => ucfirst(fake()->words(2, true)),
            'type' => AccountType::Asset,
            'is_contra' => false,
            'restriction' => null,
            'parent_id' => null,
            'is_postable' => true,
            'is_cash' => false,
            'is_active' => true,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Asset,
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Liability,
        ]);
    }

    public function netAssets(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::NetAssets,
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Revenue,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Expense,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'type' => AccountType::Asset,
            'is_cash' => true,
            'is_postable' => true,
        ]);
    }

    public function nonPostable(): static
    {
        return $this->state(fn () => [
            'is_postable' => false,
        ]);
    }

    public function contra(): static
    {
        return $this->state(fn () => [
            'is_contra' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
