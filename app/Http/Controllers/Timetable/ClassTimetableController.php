<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\SaveSlotRequest;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableSlotService;
use App\Services\Timetable\TimetableView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClassTimetableController extends Controller
{
    public function __construct(
        private readonly TimetableView $view,
        private readonly TimetableSlotService $slotService,
    ) {}

    /**
     * The class timetable editor grid (spec 13 Requirement 2, admin only).
     * Past-year classes render read-only.
     */
    public function edit(Request $request, SchoolClass $schoolClass): Response
    {
        return Inertia::render('timetable/editor', $this->view->getEditorViewData($request, $schoolClass));
    }

    /**
     * Copy the class's slots from the previous semester (AC-13-07). The
     * copied grid replaces nothing: occupied positions are skipped and
     * reported in the flash toast.
     */
    public function copy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'integer', Rule::exists(Semester::class, 'id')],
        ]);

        $result = $this->slotService->copySemesterSlots($schoolClass, (int) $validated['semester_id']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.copied', [
                'copied' => $result['copied'],
                'skipped' => count($result['skipped']),
            ]),
        ]);

        return back();
    }

    /**
     * Store a new slot in the class's timetable.
     */
    public function store(SaveSlotRequest $request, SchoolClass $schoolClass): RedirectResponse|JsonResponse
    {
        $result = $this->slotService->createSlot(
            $schoolClass,
            $request->slotAttributes(),
            $request->boolean('confirm_conflicts'),
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_created'),
        ]);

        return back();
    }

    /**
     * Update a slot in the class's timetable.
     */
    public function update(SaveSlotRequest $request, SchoolClass $schoolClass, TimetableSlot $slot): RedirectResponse|JsonResponse
    {
        abort_unless($slot->class_id === $schoolClass->id, 404);

        $result = $this->slotService->updateSlot(
            $schoolClass,
            $slot,
            $request->slotAttributes(),
            $request->boolean('confirm_conflicts'),
        );

        if ($result instanceof JsonResponse) {
            return $result;
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_updated'),
        ]);

        return back();
    }

    /**
     * Remove a slot from the class's timetable.
     */
    public function destroy(SchoolClass $schoolClass, TimetableSlot $slot): RedirectResponse
    {
        abort_unless($slot->class_id === $schoolClass->id, 404);

        $this->slotService->deleteSlot($schoolClass, $slot);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_deleted'),
        ]);

        return back();
    }
}
