<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\OverpaymentReason;
use App\Enums\OverpaymentStatus;
use App\Models\Account;
use App\Models\Guardian;
use App\Models\Overpayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Overpayment> */
class OverpaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'guardian_id' => Guardian::factory(),
            'liability_account_id' => Account::factory()->create(['type' => AccountType::Liability, 'is_cash' => false])->id,
            'deposit_account_id' => Account::factory()->cash(),
            'amount' => '2700000.00', 'bank_mutation_date' => '2026-10-20', 'posting_date' => '2026-10-20',
            'reason' => OverpaymentReason::WrongAmount, 'status' => OverpaymentStatus::Held,
            'proof_path' => 'overpayments/proof.pdf', 'proof_hash' => hash('sha256', fake()->uuid()),
            'recorded_by_user_id' => User::factory()->finance(),
        ];
    }
}
