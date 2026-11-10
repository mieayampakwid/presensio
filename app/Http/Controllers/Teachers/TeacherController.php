<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\StoreTeacherRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\Teachers\TeacherService;
use App\Services\Teachers\TeacherViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherViewService $viewService,
        private readonly TeacherService $teacherService,
    ) {}

    /**
     * Display a listing of the teachers.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        return Inertia::render('teachers/index', [
            'teachers' => $this->viewService->getPaginatedTeachers($search),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new teacher.
     */
    public function create(): Response
    {
        return Inertia::render('teachers/create');
    }

    /**
     * Store a newly created teacher.
     */
    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $this->teacherService->createTeacher($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.teachers.created')]);

        return to_route('teachers.index');
    }

    /**
     * Show the form for editing the specified teacher.
     */
    public function edit(Teacher $teacher): Response
    {
        return Inertia::render('teachers/edit', $this->viewService->getEditViewData($teacher));
    }

    /**
     * Update the specified teacher.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->teacherService->updateTeacher($teacher, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.teachers.updated')]);

        return to_route('teachers.edit', $teacher);
    }

    /**
     * Remove the specified teacher.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $blockers = $this->teacherService->deletionBlockers($teacher);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        $this->teacherService->deleteTeacher($teacher);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.teachers.deleted')]);

        return to_route('teachers.index');
    }
}
