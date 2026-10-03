<?php

namespace Database\Factories;

use App\Enums\SubjectGroup;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('???'));

        return [
            'code' => $code,
            'name' => ucfirst(fake()->unique()->word()).' '.$code,
            'group' => SubjectGroup::General,
            'sort_order' => fake()->numberBetween(1, 20),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function localContent(): static
    {
        return $this->state(fn () => [
            'group' => SubjectGroup::LocalContent,
        ]);
    }

    public function elective(): static
    {
        return $this->state(fn () => [
            'group' => SubjectGroup::Elective,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
