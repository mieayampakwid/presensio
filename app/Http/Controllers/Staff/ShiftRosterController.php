<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\BulkShiftRosterRequest;
use App\Http\Requests\Staff\SaveShiftRosterRequest;
use App\Services\Staff\ShiftRosterService;
use App\Services\Staff\ShiftRosterViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly ShiftRosterViewService $viewService,
        private readonly ShiftRosterService $rosterService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('staff/roster', $this->viewService->getRosterViewData($request));
    }

    /**
     * Upsert one roster row per (employee, date) in the range.
     */
    public function bulk(BulkShiftRosterRequest $request): RedirectResponse
    {
        $this->rosterService->bulkAssign(
            $request->validated('employees'),
            $request->dates(),
            $request->validated('shift_id'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shifts.roster.toast.bulk_assigned')]);

        return to_route('staff-roster.index', ['week' => $request->validated('start_date')]);
    }

    /**
     * Override one cell: a shift, or an explicit day off when shift_id is
     * null (spec 20 §Req 3).
     */
    public function set(SaveShiftRosterRequest $request): RedirectResponse
    {
        $this->rosterService->setCell(
            (int) $request->validated('employee_id'),
            (string) $request->validated('date'),
            $request->validated('shift_id') !== null ? (int) $request->validated('shift_id') : null,
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
        $this->rosterService->clearCell(
            (int) $request->validated('employee_id'),
            (string) $request->validated('date'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('shifts.roster.toast.cleared')]);

        return to_route('staff-roster.index', ['week' => $request->validated('date')]);
    }
}
