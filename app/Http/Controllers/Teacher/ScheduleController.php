<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\AcademicYears\SemesterService;
use App\Services\Timetable\TimetableView;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's personal weekly schedule (spec 13 Requirement 4).
 */
class ScheduleController extends Controller
{
    public function __construct(
        private readonly TimetableView $view,
    ) {}

    /**
     * A teacher without a linked profile renders an empty schedule (the
     * ClassAccess precedent: empty, not a 403).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $teacher = $user->teacher;
        $semester = app(SemesterService::class)->current();

        return Inertia::render('teacher/schedule', [
            'days' => $teacher !== null && $semester !== null
                ? $this->view->teacherSchedule($teacher, $semester)
                : [],
        ]);
    }
}
