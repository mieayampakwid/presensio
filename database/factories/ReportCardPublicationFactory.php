<?php

namespace Database\Factories;

use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportCardPublication>
 */
class ReportCardPublicationFactory extends Factory
{
    protected $model = ReportCardPublication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            // The class must belong to the same academic year as the semester.
            'class_id' => function (array $attributes) {
                /** @var Semester $semester */
                $semester = Semester::query()->findOrFail($attributes['semester_id']);
                $academicYearId = $semester->academic_year_id;

                return SchoolClass::factory()->create(['academic_year_id' => $academicYearId])->id;
            },
            'version' => 1,
            'published_at' => now(),
            'published_by_user_id' => User::factory(),
            'retracted_at' => null,
            'retracted_by_user_id' => null,
            'retract_reason' => null,
        ];
    }

    public function retracted(): static
    {
        return $this->state(fn (array $attributes) => [
            'retracted_at' => now(),
            'retracted_by_user_id' => User::factory(),
            'retract_reason' => 'Ditarik untuk perbaikan',
        ]);
    }
}
