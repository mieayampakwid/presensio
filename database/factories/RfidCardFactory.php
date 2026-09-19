<?php

namespace Database\Factories;

use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfidCard>
 */
class RfidCardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfid_number' => fake()->unique()->numerify('############'),
            'student_id' => null,
        ];
    }

    /**
     * Indicate the card is spare (the factory default, made explicit).
     */
    public function spare(): static
    {
        return $this->state(fn () => ['student_id' => null]);
    }

    /**
     * Assign the card to the given student.
     */
    public function assigned(Student $student): static
    {
        return $this->state(fn () => ['student_id' => $student->id]);
    }
}
