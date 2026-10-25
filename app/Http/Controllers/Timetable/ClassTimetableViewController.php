<?php

namespace App\Http\Controllers\Timetable;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\Attendance\ClassAccess;
use App\Services\SchoolSettings;
use App\Services\Timetable\TimetableView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The class weekly view (spec 13 Requirement 3): a read-only grid open to
 * admins and principals (all classes), teachers in scope, and the student/
 * guardian whose current class it is.
 */
class ClassTimetableViewController extends Controller
{
    public function __construct(
        private readonly TimetableView $view,
    ) {}

    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless($this->canView($user, $schoolClass), 403);

        $schoolClass->load('academicYear.semesters');
        $semester = $this->view->resolveSemester($request, $schoolClass);

        return Inertia::render('timetable/week', [
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name],
            'semesters' => $schoolClass->academicYear->semesters
                ->map(fn (Semester $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'number' => $option->number,
                ])
                ->all(),
            'semester_id' => $semester->id,
            'days' => $this->view->days($schoolClass, $semester),
        ]);
    }

    /**
     * Admin/principal see every class; teachers see classes in their
     * academic scope (homeroom union subject assignments); students and
     * guardians only the current class (classOn today) of the student or a
     * linked child.
     */
    private function canView(User $user, SchoolClass $schoolClass): bool
    {
        if ($user->hasAnyRole(UserRole::Admin, UserRole::Principal)) {
            return true;
        }

        if ($user->hasRole(UserRole::Teacher)) {
            return ClassAccess::academicClassIds($user)->contains($schoolClass->id);
        }

        if ($user->hasRole(UserRole::Student)) {
            $student = $user->student;

            return $student !== null
                && $student->classOn(app(SchoolSettings::class)->todayDate())?->id === $schoolClass->id;
        }

        if ($user->hasRole(UserRole::Parent)) {
            $guardian = $user->guardian;
            if ($guardian === null) {
                return false;
            }

            $today = app(SchoolSettings::class)->todayDate();

            return $guardian->students->contains(
                fn (Student $student): bool => $student->classOn($today)?->id === $schoolClass->id,
            );
        }

        return false;
    }
}
