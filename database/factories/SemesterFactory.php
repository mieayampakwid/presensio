<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    protected $model = Semester::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'number' => 1,
            'name' => 'Semester Ganjil',
            'starts_at' => '2026-07-01',
            'ends_at' => '2026-12-31',
        ];
    }

    public function ganjil(): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => 1,
            'name' => 'Semester Ganjil',
        ]);
    }

    public function genap(): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => 2,
            'name' => 'Semester Genap',
        ]);
    }
}
