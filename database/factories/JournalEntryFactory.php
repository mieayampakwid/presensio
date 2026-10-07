<?php

namespace Database\Factories;

use App\Enums\JournalSource;
use App\Enums\JournalStatus;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $yearMonth = fake()->date('Ym');
        $seq = fake()->unique()->numberBetween(1, 99999);

        return [
            'number' => sprintf('JU-%s-%05d', $yearMonth, $seq),
            'entry_date' => fake()->date(),
            'description' => fake()->sentence(),
            'status' => JournalStatus::Posted,
            'source_type' => JournalSource::Manual,
            'source_id' => null,
            'reverses_entry_id' => null,
            'attachment_path' => null,
            'created_by_user_id' => User::factory(),
            'posted_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'number' => null,
            'status' => JournalStatus::Draft,
            'posted_at' => null,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'source_type' => JournalSource::Manual,
        ]);
    }
}
