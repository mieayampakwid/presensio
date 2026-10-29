<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Turns (employee, date) into the effective work shift: a roster row
 * (null shift = explicit day off) beats the employee's default shift,
 * which beats the global staff hours (spec 20 §Decisions).
 */
class ShiftResolver
{
    public function __construct(private readonly SchoolSettings $settings) {}

    public function resolve(Employee $employee, string $date): ShiftResolution
    {
        return $this->resolveMany(new EloquentCollection([$employee]), $date)->get($employee->id);
    }

    /**
     * Effective shift per employee for one date, in a single roster query.
     *
     * @param  EloquentCollection<int, Employee>  $employees
     * @return Collection<int, ShiftResolution> keyed by employee_id
     */
    public function resolveMany(EloquentCollection $employees, string $date): Collection
    {
        $employees->loadMissing('shift');

        $rosters = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('date', $date)
            ->with('shift')
            ->get()
            ->keyBy('employee_id');

        return $employees
            ->keyBy('id')
            ->map(fn (Employee $employee): ShiftResolution => $this->resolutionFor($employee, $rosters->get($employee->id)));
    }

    /**
     * The working window (school timezone) on the given date: the shift's
     * start/end (end +1 day when it crosses midnight), or the global staff
     * hours when no shift applies.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function windowFor(?Shift $shift, CarbonInterface|string $date): array
    {
        if ($shift !== null) {
            return [$shift->startOn($date), $shift->endOn($date)];
        }

        $day = is_string($date) ? Date::parse($date, $this->settings->timezone()) : $date;

        return [
            $this->settings->staffStartTimeOn($day),
            $this->settings->staffEndTimeOn($day),
        ];
    }

    private function resolutionFor(Employee $employee, ?ShiftRoster $roster): ShiftResolution
    {
        if ($roster !== null) {
            return new ShiftResolution($roster->shift, $roster->shift === null);
        }

        return new ShiftResolution($employee->shift, false);
    }
}
