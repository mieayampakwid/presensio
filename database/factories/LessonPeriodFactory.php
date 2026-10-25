<?php

namespace Database\Factories;

use App\Enums\PeriodKind;
use App\Models\LessonPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonPeriod>
 */
class LessonPeriodFactory extends Factory
{
    protected $model = LessonPeriod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => PeriodKind::Lesson,
            'number' => 1,
            'name' => null,
            'start_time' => '07:00',
            'end_time' => '07:35',
            'sort_order' => 1,
        ];
    }

    public function break(): static
    {
        return $this->state(fn (): array => [
            'kind' => PeriodKind::Break,
            'number' => null,
            'name' => 'Istirahat',
        ]);
    }
}
