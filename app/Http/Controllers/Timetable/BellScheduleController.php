<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\SaveBellScheduleRequest;
use App\Models\BellSchedule;
use App\Models\LessonPeriod;
use App\Services\SchoolSettings;
use App\Services\Timetable\BellScheduleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BellScheduleController extends Controller
{
    public function __construct(private readonly BellScheduleService $service) {}

    /**
     * Display the bell schedules editor page.
     */
    public function index(): Response
    {
        return Inertia::render('timetable/bell-schedules', [
            'schedules' => BellSchedule::query()
                ->with('periods')
                ->orderBy('id')
                ->get()
                ->map(fn (BellSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'name' => $schedule->name,
                    'weekdays' => $schedule->weekdays,
                    'periods' => $schedule->periods
                        ->map(fn (LessonPeriod $period): array => [
                            'id' => $period->id,
                            'kind' => $period->kind->value,
                            'number' => $period->number,
                            'name' => $period->name,
                            'start_time' => substr((string) $period->start_time, 0, 5),
                            'end_time' => substr((string) $period->end_time, 0, 5),
                        ])
                        ->all(),
                ])
                ->all(),
            'operational_weekdays' => app(SchoolSettings::class)->operationalWeekdays(),
        ]);
    }

    /**
     * Store a newly created bell schedule.
     */
    public function store(SaveBellScheduleRequest $request): RedirectResponse
    {
        $this->service->save(null, $request->scheduleAttributes(), $request->periodRows());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.created'),
        ]);

        return to_route('bell-schedules.index');
    }

    /**
     * Update the specified bell schedule.
     */
    public function update(SaveBellScheduleRequest $request, BellSchedule $bellSchedule): RedirectResponse
    {
        $this->service->save($bellSchedule, $request->scheduleAttributes(), $request->periodRows());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.updated'),
        ]);

        return to_route('bell-schedules.index');
    }

    /**
     * Remove the specified bell schedule.
     */
    public function destroy(BellSchedule $bellSchedule): RedirectResponse
    {
        $this->service->delete($bellSchedule);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.deleted'),
        ]);

        return to_route('bell-schedules.index');
    }
}
