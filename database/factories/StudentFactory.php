<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => fake()->name(),
            'nickname' => fake()->firstName(),
            'dob' => fake()->date('Y-m-d', '2015-01-01'),
            'student_number' => fake()->unique()->numerify('00########'),
            'class_id' => null,
        ];
    }

    /**
     * Indicate the student has no student number (NIS/NISN) on file.
     */
    public function unnumbered(): static
    {
        return $this->state(fn () => ['student_number' => null]);
    }
}
