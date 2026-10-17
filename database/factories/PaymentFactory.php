<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_number' => null,
            'amount' => '350000.00',
            'payment_method' => PaymentMethod::BankTransfer,
            'deposit_account_id' => Account::factory()->cash(),
            'payment_date' => fake()->date(),
            'origin_bank' => 'BCA',
            'proof_path' => null,
            'proof_hash' => null,
            'status' => PaymentStatus::Pending,
            'reject_reason' => null,
            'void_reason' => null,
            'notes' => null,
            'submitted_by_user_id' => User::factory(),
            'verified_by_user_id' => null,
            'verified_at' => null,
            'voided_by_user_id' => null,
            'voided_at' => null,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => [
            'payment_method' => PaymentMethod::Cash,
            'origin_bank' => null,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'receipt_number' => sprintf('KWT-%s-%05d', now()->format('Ym'), fake()->unique()->numberBetween(1, 99999)),
            'status' => PaymentStatus::Verified,
            'verified_by_user_id' => User::factory(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Bukti tidak sesuai'): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Rejected,
            'reject_reason' => $reason,
        ]);
    }

    public function void(string $reason = 'Salah input'): static
    {
        return $this->verified()->state(fn () => [
            'status' => PaymentStatus::Void,
            'void_reason' => $reason,
            'voided_by_user_id' => User::factory(),
            'voided_at' => now(),
        ]);
    }
}
