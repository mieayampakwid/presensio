<?php

namespace App\Services\Staff;

use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

class ShiftRosterViewService
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly ShiftResolver $shifts,
    ) {}

    /**
     * Build the view payload for the shift roster grid.
     *
     * @return array{
     *     rows: list<array{
     *         employee_id: int,
     *         name: string,
     *         default_shift: array{id: int, name: string}|null,
     *         cells: array<int, array{
     *             date: string,
     *             editable: bool,
     *             source: string,
     *             is_day_off: bool,
     *             shift: array{id: int, name: string, start_time: string, end_time: string}|null
     *         }>
     *     }>,
     *     shifts: list<array{id: int, name: string, start_time: string, end_time: string, crosses_midnight: bool}>,
     *     view: array{start_date: string, end_date: string},
     *     today: string,
     *     can_manage: bool
     * }
     */
    public function getRosterViewData(Request $request): array
    {
        $today = $this->settings->todayDate();
        [$start, $end] = $this->resolveRange($request, $today);
        $dates = $this->datesBetween($start, $end);

        $employees = Employee::query()->active()->with('shift')->orderBy('name')->get();

        // One range query only to tell override cells apart from defaults;
        // the effective shift itself comes from resolveMany per date.
        $rostered = ShiftRoster::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn (ShiftRoster $row): string => $row->employee_id.'|'.$row->date->toDateString());

        $resolutionsByDate = [];
        foreach ($dates as $date) {
            $resolutionsByDate[$date] = $this->shifts->resolveMany($employees, $date);
        }

        $rows = array_values($employees->map(function (Employee $employee) use ($dates, $resolutionsByDate, $rostered, $today): array {
            $cells = [];
            foreach ($dates as $dateIndex => $date) {
                $resolution = $resolutionsByDate[$date]->get($employee->id);
                $hasRoster = $rostered->has($employee->id.'|'.$date);

                $cells[$dateIndex] = [
                    'date' => $date,
                    'editable' => $date >= $today,
                    'source' => $hasRoster ? 'roster' : ($employee->shift !== null ? 'default' : 'fallback'),
                    'is_day_off' => $resolution->dayOff,
                    'shift' => $resolution->shift === null ? null : [
                        'id' => $resolution->shift->id,
                        'name' => $resolution->shift->name,
                        'start_time' => $resolution->shift->start_time->format('H:i'),
                        'end_time' => $resolution->shift->end_time->format('H:i'),
                    ],
                ];
            }

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'default_shift' => $employee->shift === null
                    ? null
                    : ['id' => $employee->shift->id, 'name' => $employee->shift->name],
                'cells' => $cells,
            ];
        })->all());

        return [
            'rows' => $rows,
            'shifts' => array_values(Shift::query()->orderBy('name')->get([
                'id', 'name', 'start_time', 'end_time', 'crosses_midnight',
            ])->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time->format('H:i'),
                'end_time' => $shift->end_time->format('H:i'),
                'crosses_midnight' => $shift->crosses_midnight,
            ])->all()),
            'view' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'today' => $today,
            'can_manage' => $request->user()?->hasRole(UserRole::Admin) ?? false,
        ];
    }

    /**
     * The requested week (Monday-based) or month, defaulting to the current week.
     *
     * @return array{0: string, 1: string}
     */
    public function resolveRange(Request $request, string $today): array
    {
        $tz = $this->settings->timezone();
        $week = $request->string('week')->toString();
        $month = $request->string('month')->toString();

        if ($month !== '' && Date::hasFormat($month, 'Y-m-d') && ($week === '' || ! Date::hasFormat($week, 'Y-m-d'))) {
            $day = Date::parse($month, $tz);

            return [$day->copy()->startOfMonth()->toDateString(), $day->copy()->endOfMonth()->toDateString()];
        }

        $day = $week !== '' && Date::hasFormat($week, 'Y-m-d')
            ? Date::parse($week, $tz)
            : Date::parse($today, $tz);

        return [
            $day->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            $day->copy()->endOfWeek(CarbonInterface::SUNDAY)->toDateString(),
        ];
    }

    /**
     * Every Y-m-d date in the inclusive range.
     *
     * @return list<string>
     */
    public function datesBetween(string $start, string $end): array
    {
        $from = Date::parse($start);
        $to = Date::parse($end);

        $dates = [];
        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return $dates;
    }
}
