<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Services\Attendance\ClassAccess;
use App\Services\Courses\CourseViewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseViewService $courseViewService,
    ) {}

    /**
     * Display a listing of courses in the active year.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $this->courseViewService->listForUser($user);

        return Inertia::render('teacher/courses/index', $data);
    }

    /**
     * Display course workspace: course info + enrolled students.
     */
    public function show(Request $request, ClassSubject $classSubject): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        abort_unless(
            ClassAccess::academicClassIds($user)->contains($classSubject->class_id),
            403
        );

        $data = $this->courseViewService->buildWorkspace($classSubject, $user);

        return Inertia::render('teacher/courses/show', $data);
    }
}
