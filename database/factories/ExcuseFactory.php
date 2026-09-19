<?php

namespace Database\Factories;

use App\Enums\ExcuseStatus;
use App\Enums\ExcuseType;
use App\Models\Excuse;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Excuse>
 */
class ExcuseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'type' => ExcuseType::Sick,
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'reason' => fake()->sentence(),
            'attachment_path' => null,
            'status' => ExcuseStatus::Pending,
            'review_note' => null,
            'reviewed_by_user_id' => null,
        ];
    }

    public function leave(): static
    {
        return $this->state(fn () => ['type' => ExcuseType::Leave]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ExcuseStatus::Approved,
            'reviewed_by_user_id' => User::factory()->admin(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ExcuseStatus::Rejected,
            'reviewed_by_user_id' => User::factory()->admin(),
        ]);
    }

    /**
     * Opaque local-disk path shape the controller stores (the file itself
     * only exists in tests that pair this with Storage::fake + a real put).
     */
    public function withAttachment(): static
    {
        return $this->state(fn () => [
            'attachment_path' => 'excuses/'.bin2hex(random_bytes(20)).'.jpg',
        ]);
    }
}
