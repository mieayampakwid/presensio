<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\SaveSlotRequest;
use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableCopier;
use App\Services\Timetable\TimetableValidator;
use App\Services\Timetable\TimetableView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassTimetableController extends Controller
{
    public function __construct(
        private readonly TimetableValidator $validator,
        private readonly TimetableCopier $copier,
        private readonly TimetableView $view,
    ) {}

    /**
     * The class timetable editor grid (spec 13 Requirement 2, admin only).
     * Past-year classes render read-only.
     */
    public function edit(Request $request, SchoolClass $schoolClass): Response
    {
        $schoolClass->load([
            'academicYear.semesters',
            'classSubjects.subject:id,name',
            'classSubjects.teacher.employee:id,name',
        ]);

        $semester = $this->view->resolveSemester($request, $schoolClass);

        return Inertia::render('timetable/editor', [
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'class_subjects' => $schoolClass->classSubjects
                ->map(fn (ClassSubject $assignment): array => [
                    'id' => $assignment->id,
                    'subject_name' => $assignment->subject->name,
                    'teacher_name' => $assignment->teacher?->employee?->name,
                ])
                ->all(),
            'semesters' => $schoolClass->academicYear->semesters
                ->map(fn (Semester $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'number' => $option->number,
                ])
                ->all(),
            'semester_id' => $semester->id,
            'read_only' => ! $schoolClass->academicYear->is_active,
            'days' => $this->view->days($schoolClass, $semester),
        ]);
    }

    /**
     * Copy the class's slots from the previous semester (AC-13-07). The
     * copied grid replaces nothing: occupied positions are skipped and
     * reported in the flash toast.
     */
    public function copy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        if (! $schoolClass->academicYear?->is_active) {
            throw ValidationException::withMessages([
                'class_id' => __('timetable.errors.slot_past_year'),
            ]);
        }

        $validated = $request->validate([
            'semester_id' => ['required', 'integer', Rule::exists(Semester::class, 'id')],
        ]);

        $semester = Semester::findOrFail((int) $validated['semester_id']);
        if ($semester->academic_year_id !== $schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'semester_id' => __('timetable.errors.slot_semester_wrong_year'),
            ]);
        }

        $result = $this->copier->copy($schoolClass, $semester);

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
        $conflictResponse = $this->checkPlacement($request, $schoolClass, null);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        TimetableSlot::create($request->slotAttributes() + ['class_id' => $schoolClass->id]);

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

        $conflictResponse = $this->checkPlacement($request, $schoolClass, $slot);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        $slot->fill($request->slotAttributes())->save();

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

        if (! $schoolClass->academicYear?->is_active) {
            abort(422, __('timetable.errors.slot_past_year'));
        }

        $slot->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('timetable.toast.slot_deleted'),
        ]);

        return back();
    }

    /**
     * Run the validator: hard errors throw a 422 validation error; teacher/
     * room warnings return a 409 carrying the conflict list unless the admin
     * confirmed them (AC-13-04).
     *
     * @return JsonResponse|null a 409 response when unconfirmed conflicts exist
     */
    private function checkPlacement(SaveSlotRequest $request, SchoolClass $schoolClass, ?TimetableSlot $slot): ?JsonResponse
    {
        $attributes = $request->slotAttributes();
        $semester = Semester::findOrFail($attributes['semester_id']);
        $startPeriod = LessonPeriod::findOrFail($attributes['lesson_period_id']);
        $classSubject = $attributes['class_subject_id'] !== null
            ? ClassSubject::findOrFail($attributes['class_subject_id'])
            : null;

        $errors = $this->validator->errors(
            $schoolClass,
            $semester,
            $attributes['day_of_week'],
            $startPeriod,
            $attributes['period_count'],
            $slot?->id,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['slot' => $errors]);
        }

        $conflicts = $this->validator->conflicts(
            $schoolClass,
            $semester,
            $attributes['day_of_week'],
            $startPeriod,
            $attributes['period_count'],
            $classSubject,
            $attributes['room'],
        );

        if ($conflicts !== [] && ! $request->boolean('confirm_conflicts')) {
            return response()->json(['conflicts' => $conflicts], 409);
        }

        return null;
    }
}
