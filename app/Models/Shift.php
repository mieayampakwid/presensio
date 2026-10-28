<?php

namespace App\Models;

use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;

/**
 * A named shift template — the time part of a work schedule; whether it
 * applies on a date is resolved per employee by ShiftResolver (spec 20).
 *
 * @property int $id
 * @property string $name
 * @property CarbonInterface $start_time
 * @property CarbonInterface $end_time
 * @property bool $crosses_midnight
 * @property int $sweep_offset_minutes
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read Employee $employees
 * @property-read ShiftRoster $rosters
 * @property-read EmployeeAttendance $attendances
 */
#[Fillable(['name', 'start_time', 'end_time', 'crosses_midnight', 'sweep_offset_minutes'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'crosses_midnight' => 'boolean',
            'sweep_offset_minutes' => 'integer',
        ];
    }

    /**
     * The shift start instant on the given day (school timezone when a
     * date string is given).
     */
    public function startOn(CarbonInterface|string $day): CarbonInterface
    {
        return $this->asDay($day)->setTimeFromTimeString($this->start_time->format('H:i'));
    }

    /**
     * The shift end instant on the given day, +1 calendar day when the
     * shift crosses midnight.
     */
    public function endOn(CarbonInterface|string $day): CarbonInterface
    {
        $end = $this->asDay($day)->setTimeFromTimeString($this->end_time->format('H:i'));

        return $this->crosses_midnight ? $end->addDay() : $end;
    }

    /**
     * Cron sweep time (H:i) = start + offset. Save-time validation keeps
     * start + offset on the start date, so the run sweeps the shift's own
     * start date.
     */
    public function sweepCronTime(): string
    {
        return $this->start_time->copy()->addMinutes($this->sweep_offset_minutes)->format('H:i');
    }

    /**
     * Employees whose default shift is this one.
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Roster rows assigning this shift.
     *
     * @return HasMany<ShiftRoster, $this>
     */
    public function rosters(): HasMany
    {
        return $this->hasMany(ShiftRoster::class);
    }

    /**
     * Attendance records snapshotting this shift.
     *
     * @return HasMany<EmployeeAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(EmployeeAttendance::class);
    }

    /**
     * Parse a Y-m-d string in the school timezone, like SchoolSettings::isSchoolDay.
     */
    private function asDay(CarbonInterface|string $day): CarbonInterface
    {
        return is_string($day)
            ? Date::parse($day, app(SchoolSettings::class)->timezone())
            : $day;
    }
}
