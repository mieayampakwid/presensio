<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Score;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Score>
 */
class ScoreFactory extends Factory
{
    protected $model = Score::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'student_id' => Student::factory(),
            'score' => '85.00',
            'remedial_score' => null,
            'notes' => null,
            'graded_by_user_id' => User::factory(),
        ];
    }
}
