<?php

namespace Database\Factories;

use App\Enums\Curriculum;
use App\Models\ReportCard;
use App\Models\ReportCardPublication;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCard>
 */
class ReportCardFactory extends Factory
{
    protected $model = ReportCard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_card_publication_id' => ReportCardPublication::factory(),
            'student_id' => Student::factory(),
            // Match the curriculum of the publication's class.
            'curriculum' => function (array $attributes) {
                $publication = ReportCardPublication::find($attributes['report_card_publication_id']);
                /** @var Curriculum $curriculum */
                $curriculum = $publication->schoolClass->curriculum;

                return $curriculum->value;
            },
            'payload' => [
                'student' => [
                    'name' => fake()->name(),
                ],
            ],
        ];
    }
}
