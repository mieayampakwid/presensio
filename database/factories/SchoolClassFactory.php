<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kelas '.fake()->unique()->numerify('#'),
            'teacher_id' => null,
        ];
    }

    /**
     * Assign the given homeroom teacher.
     */
    public function withTeacher(Teacher $teacher): static
    {
        return $this->state(fn () => ['teacher_id' => $teacher->id]);
    }
}
