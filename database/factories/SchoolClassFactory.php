<?php

namespace Database\Factories;

use App\Enums\Curriculum;
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
        $grade = fake()->numberBetween(1, 12);

        return [
            'academic_year_id' => fn () => AcademicYear::active()->id
                ?? AcademicYear::factory()->current()->create()->id,
            'name' => $grade.' '.fake()->unique()->regexify('[A-C]'),
            'grade_level' => $grade,
            'curriculum' => Curriculum::Merdeka,
            'teacher_id' => null,
        ];
    }

    public function withTeacher(Teacher $teacher): static
    {
        return $this->state(fn () => ['teacher_id' => $teacher->id]);
    }
}
