<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalLine>
 */
class JournalLineFactory extends Factory
{
    protected $model = JournalLine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isDebit = fake()->boolean();
        $amount = fake()->numberBetween(10000, 500000);

        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'debit' => $isDebit ? sprintf('%d.00', $amount) : '0.00',
            'credit' => ! $isDebit ? sprintf('%d.00', $amount) : '0.00',
            'student_id' => null,
            'bill_id' => null,
            'memo' => fake()->optional()->sentence(),
        ];
    }

    public function debit(string|int $amount): static
    {
        $formatted = sprintf('%.2f', (float) $amount);

        return $this->state(fn () => [
            'debit' => $formatted,
            'credit' => '0.00',
        ]);
    }

    public function credit(string|int $amount): static
    {
        $formatted = sprintf('%.2f', (float) $amount);

        return $this->state(fn () => [
            'debit' => '0.00',
            'credit' => $formatted,
        ]);
    }
}
