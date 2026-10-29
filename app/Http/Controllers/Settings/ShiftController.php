<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveShiftRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ShiftController extends Controller
{
    /**
     * Store a newly created shift template (spec 20 §Req 1).
     */
    public function store(SaveShiftRequest $request): RedirectResponse
    {
        if (Shift::count() >= 10) {
            throw ValidationException::withMessages([
                'shift' => __('shifts.errors.max_shifts'),
            ]);
        }

        Shift::create($request->shiftAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('shifts.toast.created'),
        ]);

        return to_route('attendance-settings.edit');
    }

    /**
     * Update the specified shift template.
     */
    public function update(SaveShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->shiftAttributes());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('shifts.toast.updated'),
        ]);

        return to_route('attendance-settings.edit');
    }

    /**
     * Remove the specified shift template; referenced shifts are blocked.
     */
    public function destroy(Shift $shift): RedirectResponse
    {
        if ($shift->employees()->exists() || $shift->rosters()->exists() || $shift->attendances()->exists()) {
            throw ValidationException::withMessages([
                'shift' => __('shifts.errors.in_use'),
            ]);
        }

        $shift->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('shifts.toast.deleted'),
        ]);

        return to_route('attendance-settings.edit');
    }
}
