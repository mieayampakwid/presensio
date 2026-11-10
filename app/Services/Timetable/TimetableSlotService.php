<?php

namespace App\Services\Timetable;

use App\Models\ClassSubject;
use App\Models\LessonPeriod;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TimetableSlotService
{
    public function __construct(
        private readonly TimetableValidator $validator,
        private readonly TimetableCopier $copier,
    ) {}

    /**
     * Run the validator: hard errors throw a 422 validation error; teacher/
     * room warnings return a 409 carrying the conflict list unless confirmed (AC-13-04).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function checkPlacement(SchoolClass $schoolClass, array $attributes, ?TimetableSlot $slot, bool $confirmConflicts): ?JsonResponse
    {
        /** @var Semester $semester */
        $semester = Semester::findOrFail($attributes['semester_id']);
        /** @var LessonPeriod $startPeriod */
        $startPeriod = LessonPeriod::findOrFail($attributes['lesson_period_id']);
        /** @var ClassSubject|null $classSubject */
        $classSubject = $attributes['class_subject_id'] !== null
            ? ClassSubject::findOrFail($attributes['class_subject_id'])
            : null;

        $errors = $this->validator->errors(
            $schoolClass,
            $semester,
            (int) $attributes['day_of_week'],
            $startPeriod,
            (int) $attributes['period_count'],
            $slot?->id,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['slot' => $errors]);
        }

        $conflicts = $this->validator->conflicts(
            $schoolClass,
            $semester,
            (int) $attributes['day_of_week'],
            $startPeriod,
            (int) $attributes['period_count'],
            $classSubject,
            $attributes['room'] ?? null,
        );

        if ($conflicts !== [] && ! $confirmConflicts) {
            return response()->json(['conflicts' => $conflicts], 409);
        }

        return null;
    }

    /**
     * Store a new slot in the class's timetable or return conflict response.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createSlot(SchoolClass $schoolClass, array $attributes, bool $confirmConflicts): TimetableSlot|JsonResponse
    {
        $conflictResponse = $this->checkPlacement($schoolClass, $attributes, null, $confirmConflicts);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        return TimetableSlot::create($attributes + ['class_id' => $schoolClass->id]);
    }

    /**
     * Update an existing slot in the class's timetable or return conflict response.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateSlot(SchoolClass $schoolClass, TimetableSlot $slot, array $attributes, bool $confirmConflicts): TimetableSlot|JsonResponse
    {
        $conflictResponse = $this->checkPlacement($schoolClass, $attributes, $slot, $confirmConflicts);
        if ($conflictResponse !== null) {
            return $conflictResponse;
        }

        $slot->fill($attributes)->save();

        return $slot;
    }

    /**
     * Remove a slot from the class's timetable.
     */
    public function deleteSlot(SchoolClass $schoolClass, TimetableSlot $slot): void
    {
        if (! $schoolClass->academicYear?->is_active) {
            abort(422, __('timetable.errors.slot_past_year'));
        }

        $slot->delete();
    }

    /**
     * Copy the class's slots from the previous semester.
     *
     * @return array{copied: int, skipped: list<mixed>}
     */
    public function copySemesterSlots(SchoolClass $schoolClass, int $semesterId): array
    {
        if (! $schoolClass->academicYear?->is_active) {
            throw ValidationException::withMessages([
                'class_id' => __('timetable.errors.slot_past_year'),
            ]);
        }

        /** @var Semester $semester */
        $semester = Semester::findOrFail($semesterId);
        if ($semester->academic_year_id !== $schoolClass->academic_year_id) {
            throw ValidationException::withMessages([
                'semester_id' => __('timetable.errors.slot_semester_wrong_year'),
            ]);
        }

        return $this->copier->copy($schoolClass, $semester);
    }
}
