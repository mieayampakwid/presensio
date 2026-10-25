<?php

namespace App\Services\Timetable;

use App\Models\Student;
use App\Services\AcademicYears\SemesterService;
use App\Services\SchoolSettings;

/**
 * Dashboard "Jadwal Hari Ini" (spec 13 Requirement 5): today's slots for a
 * student's classOn(today) class in the current semester — empty when today
 * is not a school day (spec 03) or nothing resolves (S-05).
 */
class TodaySchedule
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly SemesterService $semesters,
        private readonly TimetableView $view,
    ) {}

    /**
     * @return list<array{id: int, class_name: string, title: string, teacher_name: string|null, room: string|null, start_time: string, end_time: string, status: string|null}>
     */
    public function forStudent(Student $student): array
    {
        $today = $this->settings->todayDate();

        if (! $this->settings->isSchoolDay($today)) {
            return [];
        }

        $class = $student->classOn($today);
        $semester = $this->semesters->current();

        if ($class === null || $semester === null) {
            return [];
        }

        return $this->view->todaySlots($class, $semester, $this->settings->now()->dayOfWeekIso);
    }
}
