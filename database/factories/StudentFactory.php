<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\EnrollmentService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'full_name' => fake()->name(),
            'nickname' => fake()->firstName(),
            'dob' => fake()->date('Y-m-d', '2015-01-01'),
            'student_number' => fake()->unique()->numerify('00########'),
        ];
    }

    public function unnumbered(): static
    {
        return $this->state(fn () => ['student_number' => null]);
    }

    /**
     * Enroll the student into a class through the sole enrollment writer.
     * The enrollment starts on the class year's first day (long past) so
     * date-effective lookups resolve for record dates before today.
     */
    public function enrolledIn(SchoolClass $class, ?string $startedOn = null): static
    {
        return $this->afterCreating(function (Student $student) use ($class, $startedOn) {
            app(EnrollmentService::class)->assign(
                $student,
                $class,
                $startedOn ?? $class->academicYear->starts_at->toDateString(),
            );
        });
    }
}
