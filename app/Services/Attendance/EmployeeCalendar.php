<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class EmployeeCalendar
{
    public function __construct(
        private readonly SchoolSettings $settings,
    ) {}

    /**
     * Check if an employee is expected to work on the given date.
     * An employee is expected on a date when that date is a school day
     * (isSchoolDay) AND a working day for them (spec 16 §Decisions).
     */
    public function isExpected(Employee $employee, CarbonInterface|string $date): bool
    {
        if (! $this->settings->isSchoolDay($date)) {
            return false;
        }

        $day = is_string($date)
            ? Date::parse($date, $this->settings->timezone())->startOfDay()
            : $date->copy()->tz($this->settings->timezone())->startOfDay();

        return in_array($day->dayOfWeekIso, $this->workingDaysFor($employee), true);
    }

    /**
     * Effective working days for the employee (ISO weekdays 1..7).
     * Null or empty = all school operational days.
     *
     * @return list<int>
     */
    public function workingDaysFor(Employee $employee): array
    {
        $days = $employee->working_days;

        if ($days !== null && $days !== []) {
            return array_map('intval', $days);
        }

        return array_values($this->settings->operationalWeekdays());
    }

    /**
     * Get all dates an employee is expected within a given range [startDate, endDate].
     *
     * @return Collection<int, string> List of 'Y-m-d' date strings
     */
    public function expectedDatesInRange(
        Employee $employee,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate
    ): Collection {
        $tz = $this->settings->timezone();
        $start = is_string($startDate) ? Date::parse($startDate, $tz)->startOfDay() : $startDate->copy()->tz($tz)->startOfDay();
        $end = is_string($endDate) ? Date::parse($endDate, $tz)->startOfDay() : $endDate->copy()->tz($tz)->startOfDay();

        $expected = collect();
        $current = $start->copy();

        while ($current->lessThanOrEqualTo($end)) {
            if ($this->isExpected($employee, $current)) {
                $expected->push($current->toDateString());
            }
            $current = $current->addDay();
        }

        return $expected;
    }
}
