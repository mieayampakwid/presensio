<?php

namespace Database\Factories;

use App\Enums\AssessmentKind;
use App\Models\Assessment;
use App\Models\ClassSubject;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    protected $model = Assessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_subject_id' => ClassSubject::factory(),
            'semester_id' => Semester::factory(),
            'name' => fake()->words(3, true),
            'kind' => AssessmentKind::SummativeScope,
            'assessed_on' => fake()->date(),
            'max_score' => '100.00',
            'released_at' => null,
            'sort_order' => 0,
        ];
    }
}
