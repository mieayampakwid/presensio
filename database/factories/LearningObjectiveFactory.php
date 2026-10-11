<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\LearningObjective;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningObjective>
 */
class LearningObjectiveFactory extends Factory
{
    protected $model = LearningObjective::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'subject_id' => Subject::factory(),
            'grade_level' => 5,
            'code' => 'TP '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
