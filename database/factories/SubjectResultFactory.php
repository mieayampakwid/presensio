<?php

namespace Database\Factories;

use App\Models\CourseSemester;
use App\Models\Student;
use App\Models\SubjectResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubjectResult>
 */
class SubjectResultFactory extends Factory
{
    protected $model = SubjectResult::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_semester_id' => CourseSemester::factory(),
            'student_id' => Student::factory(),
            'final_score' => 85,
            'description' => fake()->sentence(),
            'description_edited' => false,
        ];
    }
}
