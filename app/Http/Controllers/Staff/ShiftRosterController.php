<?php

namespace App\Http\Controllers\Staff;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BulkShiftRosterRequest;
use App\Http\Requests\Staff\SaveShiftRosterRequest;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftRoster;
use App\Services\Attendance\ShiftResolver;
use App\Services\SchoolSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Weekly/monthly shift roster grid (spec 20 §Req 3): rows are active
 * employees, columns are dates, each cell resolves roster > default
 * shift > fallback. Only today and future dates are editable; past
 * dates are read-only.
 */
class ShiftRosterController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly ShiftResolver $shifts,
    ) {}

    public function index(Request $request): Response
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

        $rows = $employees->map(fn (Employee $employee): array => [
            'employee_id' => $employee->id,
            'name' => $employee->name,
            'default_shift' => $employee->shift === null
                ? null
                : ['id' => $employee->shift->id, 'name' => $employee->shift->name],
            'cells' => [],
        ])->all();

        foreach ($dates as $dateIndex => $date) {
            $resolutions = $this->shifts->resolveMany($employees, $date);

            foreach ($employees as $rowIndex => $employee) {
                $resolution = $resolutions->get($employee->id);
                $hasRoster = $rostered->has($employee->id.'|'.$date);

                $rows[$rowIndex]['cells'][$dateIndex] = [
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
        }

        return Inertia::render('staff/roster', [
            'rows' => $rows,
            'shifts' => Shift::query()->orderBy('name')->get([
                'id', 'name', 'start_time', 'end_time', 'crosses_midnight',
            ])->map(fn (Shift $shift): array => [
                'id' => $shift->id,
                'name' => $shift->name,
                'start_time' => $shift->start_time->format('H:i'),
                'end_time' => $shift->end_time->format('H:i'),
                'crosses_midnight' => $shift->crosses_midnight,
            ]),
            'view' => [
                'start_date' => $start,
                'end_date' => $end,
            ],
            'today' => $today,
            'can_manage' => $request->user()?->hasRole(UserRole::Admin) ?? false,
        ]);
    }

    /**
     * Upsert one roster row per (employee, date) in the range.
     */
    public function bulk(BulkShiftRosterRequest $request): RedirectResponse
    {
        $rows = [];
        foreach ($request->validated('employees') as $employeeId) {
            foreach ($request->dates() as $date) {
                $rows[] = ['employee_id' => $employeeId, 'date' => $date, 'shift_id' => $request->validated('shift_id')];
            }
        }

        DB::transaction(fn (): int => ShiftRoster::upsert($rows, ['employee_id', 'date'], ['shift_id']));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shifts.roster.toast.bulk_assigned')]);

        return to_route('staff-roster.index', ['week' => $request->validated('start_date')]);
    }

    /**
     * Override one cell: a shift, or an explicit day off when shift_id is
     * null (spec 20 §Req 3).
     */
    public function set(SaveShiftRosterRequest $request): RedirectResponse
    {
        ShiftRoster::updateOrCreate(
            [
                'employee_id' => $request->validated('employee_id'),
                'date' => $request->validated('date'),
            ],
            ['shift_id' => $request->validated('shift_id')],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shifts.roster.toast.assigned')]);

        return to_route('staff-roster.index', ['week' => $request->validated('date')]);
    }

    /**
     * Remove a roster row — the cell falls back to the default shift
     * (AC-20-06).
     */
    public function clear(SaveShiftRosterRequest $request): RedirectResponse
    {
        ShiftRoster::query()
            ->where('employee_id', $request->validated('employee_id'))
            ->whereDate('date', $request->validated('date'))
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shifts.roster.toast.cleared')]);

        return to_route('staff-roster.index', ['week' => $request->validated('date')]);
    }

    /**
     * The requested week (Monday-based) or month, defaulting to the
     * current week.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveRange(Request $request, string $today): array
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
    private function datesBetween(string $start, string $end): array
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
