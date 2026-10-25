<?php

namespace Tests\Concerns;

use App\Enums\PeriodKind;
use App\Models\BellSchedule;
use App\Models\LessonPeriod;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * Bell schedule fixture shared by the timetable feature tests.
 */
trait CreatesBellSchedules
{
    private function createSchedule(string $name, array $weekdays, int $lessons, ?int $breakAfterLesson = null): BellSchedule
    {
        return BellSchedule::factory()
            ->has(
                LessonPeriod::factory()
                    ->count($breakAfterLesson === null ? $lessons : $lessons + 1)
                    ->sequence(function (Sequence $sequence) use ($breakAfterLesson): array {
                        $position = $sequence->index + 1;
                        $isBreak = $breakAfterLesson !== null && $position === $breakAfterLesson + 1;

                        if ($isBreak) {
                            return [
                                'kind' => PeriodKind::Break,
                                'number' => null,
                                'name' => 'Istirahat',
                                'sort_order' => $position,
                            ];
                        }

                        return [
                            'kind' => PeriodKind::Lesson,
                            'number' => $breakAfterLesson !== null && $position > $breakAfterLesson
                                ? $position - 1
                                : $position,
                            'name' => null,
                            'sort_order' => $position,
                        ];
                    }),
                'periods'
            )
            ->create(['name' => $name, 'weekdays' => $weekdays]);
    }
}
