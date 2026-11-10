<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreStudentRequest;
use App\Http\Requests\Students\UpdateStudentRequest;
use App\Models\Student;
use App\Services\Students\StudentService;
use App\Services\Students\StudentViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentViewService $viewService,
        private readonly StudentService $studentService,
    ) {}

    /**
     * Display a listing of the students.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        return Inertia::render('students/index', [
            'students' => $this->viewService->getPaginatedStudents($search, $status),
            'filters' => [
                'search' => $search,
                'status' => $status !== '' ? $status : 'all',
            ],
        ]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create(): Response
    {
        return Inertia::render('students/create', $this->viewService->getFormOptions());
    }

    /**
     * Store a newly created student.
     */
    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = $this->studentService->createStudent(
            $request->studentAttributes(),
            $request->guardianPivotData(),
            $request->classId(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.students.created', ['name' => $student->full_name])]);

        return to_route('students.index');
    }

    /**
     * Show the form for editing the specified student.
     */
    public function edit(Student $student): Response
    {
        return Inertia::render('students/edit', $this->viewService->getEditViewData($student));
    }

    /**
     * Update the specified student.
     */
    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $this->studentService->updateStudent(
            $student,
            $request->studentAttributes(),
            $request->guardianPivotData(),
            $request->classId(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.students.updated')]);

        return to_route('students.edit', $student);
    }

    /**
     * Remove the specified student.
     */
    public function destroy(Student $student): RedirectResponse
    {
        $blockers = $this->studentService->deletionBlockers($student);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        $this->studentService->deleteStudent($student);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.students.deleted')]);

        return to_route('students.index');
    }
}
