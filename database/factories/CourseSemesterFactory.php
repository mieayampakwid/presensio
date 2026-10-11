<?php

namespace Database\Factories;

use App\Models\ClassSubject;
use App\Models\CourseSemester;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSemester>
 */
class CourseSemesterFactory extends Factory
{
    protected $model = CourseSemester::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_subject_id' => ClassSubject::factory(),
            'semester_id' => Semester::factory(),
            'scope_weight' => '50.00',
            'final_weight' => '50.00',
            'finalized_at' => null,
            'finalized_by_user_id' => null,
        ];
    }
}
