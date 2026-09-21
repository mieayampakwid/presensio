<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            // The bootstrap migration guarantees an active year exists.
            'academic_year_id' => fn () => AcademicYear::active()->id,
            'name' => 'Kelas '.fake()->unique()->numerify('#'),
            'teacher_id' => null,
        ];
    }

    public function withTeacher(Teacher $teacher): static
    {
        return $this->state(fn () => ['teacher_id' => $teacher->id]);
    }
}
