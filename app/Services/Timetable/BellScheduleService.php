<?php

namespace App\Services\Timetable;

use App\Models\BellSchedule;
use App\Models\LessonPeriod;
use App\Services\SchoolSettings;
use Illuminate\Support\Facades\DB;

class BellScheduleService
{
    /**
     * Create or update a bell schedule and sync its ordered periods (payload
     * order = sort order). Rows carrying an existing period id are updated in
     * place so timetable slots (Task 2) keep their references; rows absent
     * from the payload are deleted.
     *
     * @param  array{name: string, weekdays: list<int>}  $attributes
     * @param  list<array{id: int|null, kind: string, number: int|null, name: string|null, start_time: string, end_time: string}>  $periods
     */
    public function save(?BellSchedule $schedule, array $attributes, array $periods): BellSchedule
    {
        return DB::transaction(function () use ($schedule, $attributes, $periods): BellSchedule {
            $isNew = $schedule === null;
            $schedule ??= new BellSchedule;
            $schedule->fill($attributes)->save();

            $this->syncPeriods($schedule, $periods);

            return $schedule;
        });
    }

    /**
     * Delete a bell schedule. The union of the remaining schedules' weekdays
     * must still cover every operational weekday exactly (AC-13-01).
     */
    public function delete(BellSchedule $schedule): void
    {
        $operational = collect(app(SchoolSettings::class)->operationalWeekdays());
        $remaining = BellSchedule::query()
            ->whereKeyNot($schedule->getKey())
            ->get('weekdays')
            ->pluck('weekdays')
            ->flatten()
            ->map(fn ($day): int => (int) $day)
            ->unique()
            ->values();

        if ($operational->diff($remaining)->isNotEmpty()) {
            abort(422, __('timetable.errors.delete_breaks_coverage'));
        }

        $schedule->delete();
    }

    /**
     * @param  list<array{id: int|null, kind: string, number: int|null, name: string|null, start_time: string, end_time: string}>  $periods
     */
    private function syncPeriods(BellSchedule $schedule, array $periods): void
    {
        $existing = $schedule->periods()->get()->keyBy('id');
        $keepIds = array_filter(array_column($periods, 'id'));

        foreach ($existing as $period) {
            if (in_array($period->id, $keepIds, true)) {
                continue;
            }

            if ($period->isInUse()) {
                abort(422, __('timetable.errors.period_in_use'));
            }

            $period->delete();
        }

        // Shift surviving sort orders out of the way so reordering never trips
        // UNIQUE (bell_schedule_id, sort_order) mid-sync (sqlite/pgsql check
        // unique per row). tinyint: relies on pgsql smallint/sqlite, not MySQL.
        // Upgrade path: a DEFERRABLE unique constraint.
        $schedule->periods()->update(['sort_order' => DB::raw('sort_order + 1000')]);

        foreach ($periods as $index => $row) {
            $attributes = [
                'kind' => $row['kind'],
                'number' => $row['number'],
                'name' => $row['name'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'sort_order' => $index + 1,
            ];

            $period = $row['id'] !== null ? $existing->get($row['id']) : null;
            if ($period instanceof LessonPeriod) {
                // Query update, not $period->update(): the model's in-memory
                // sort_order matches the target, so Eloquent would skip the
                // column as not-dirty and leave the shifted value in place.
                LessonPeriod::query()->whereKey($period->getKey())->update($attributes);
            } else {
                $schedule->periods()->create($attributes);
            }
        }
    }
}
