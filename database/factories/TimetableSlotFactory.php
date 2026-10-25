<?php

namespace Database\Factories;

use App\Models\BellSchedule;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<TimetableSlot>
 */
class TimetableSlotFactory extends Factory
{
    protected $model = TimetableSlot::class;

    /**
     * Define the model's default state: an activity slot ("Upacara Bendera")
     * anchored at lesson 1 of a fresh three-lesson schedule on Monday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'class_id' => SchoolClass::factory(),
            'day_of_week' => 1,
            'lesson_period_id' => fn (): int => BellSchedule::factory()
                ->has(
                    LessonPeriod::factory()
                        ->count(3)
                        ->sequence(fn (Sequence $sequence): array => [
                            'number' => $sequence->index + 1,
                            'sort_order' => $sequence->index + 1,
                        ]),
                    'periods'
                )
                ->create()
                ->periods->firstWhere('number', 1)->id,
            'period_count' => 1,
            'class_subject_id' => null,
            'activity_name' => 'Upacara Bendera',
            'room' => null,
        ];
    }

    /**
     * A subject slot for the given class subject (activity name cleared).
     */
    public function forClassSubject(ClassSubject $classSubject): static
    {
        return $this->state(fn (): array => [
            'class_subject_id' => $classSubject->id,
            'activity_name' => null,
        ]);
    }
}
