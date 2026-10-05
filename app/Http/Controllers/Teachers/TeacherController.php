<?php

namespace App\Http\Controllers\Teachers;

use App\Enums\EmploymentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\StoreTeacherRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Models\Employee;
use App\Models\Teacher;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    /**
     * Display a listing of the teachers.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $teachers = Teacher::query()
            ->with('employee')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->whereHas('employee', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'name' => $teacher->employee->name,
                'teacher_number' => $teacher->employee?->employee_number,
                'phone_number' => $teacher->employee?->phone_number,
            ])
            ->withQueryString();

        return Inertia::render('teachers/index', [
            'teachers' => $teachers,
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
        DB::transaction(function () use ($request): void {
            $number = $request->validated('teacher_number') ?? $request->validated('employee_number');
            $type = $request->validated('employment_type') ? EmploymentType::from($request->validated('employment_type')) : EmploymentType::Permanent;
            $employee = Employee::create([
                'name' => $request->validated('name'),
                'employee_number' => $number,
                'phone_number' => $request->validated('phone_number'),
                'employment_type' => $type,
                'position' => $request->validated('position') ?? 'Guru',
                'working_days' => $request->validated('working_days'),
                'is_active' => $request->boolean('is_active', true),
            ]);

            Teacher::create([
                'employee_id' => $employee->id,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teacher created.']);

        return to_route('teachers.index');
    }

    /**
     * Show the form for editing the specified teacher.
     */
    public function edit(Teacher $teacher): Response
    {
        $teacher->loadMissing('employee');

        return Inertia::render('teachers/edit', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->employee->name,
                'teacher_number' => $teacher->employee?->employee_number,
                'phone_number' => $teacher->employee?->phone_number,
            ],
        ]);
    }

    /**
     * Update the specified teacher.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        DB::transaction(function () use ($request, $teacher): void {
            $teacher->loadMissing('employee');
            $number = $request->validated('teacher_number') ?? $request->validated('employee_number');
            $updateData = [
                'name' => $request->validated('name'),
                'employee_number' => $number,
                'phone_number' => $request->validated('phone_number'),
            ];
            if ($request->filled('employment_type')) {
                $updateData['employment_type'] = EmploymentType::from($request->validated('employment_type'));
            }
            if ($request->has('position')) {
                $updateData['position'] = $request->validated('position');
            }
            if ($request->has('working_days')) {
                $updateData['working_days'] = $request->validated('working_days');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            $teacher->employee?->update($updateData);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teacher updated.']);

        return to_route('teachers.edit', $teacher);
    }

    /**
     * Remove the specified teacher.
     */
    public function destroy(Teacher $teacher): RedirectResponse
    {
        $blockers = $this->deletionBlockers($teacher);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        DB::transaction(function () use ($teacher): void {
            $teacher->loadMissing('employee');
            $employee = $teacher->employee;

            $teacher->delete();

            if ($employee !== null && $employee->user_id === null) {
                $employee->delete();
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teacher deleted.']);

        return to_route('teachers.index');
    }

    /**
     * Reasons the teacher cannot be deleted, in display order.
     *
     * @return list<string>
     */
    private function deletionBlockers(Teacher $teacher): array
    {
        $blockers = [];

        if ($teacher->classes()->exists()) {
            $blockers[] = 'Cannot delete: this teacher is the homeroom teacher of a class.';
        }

        if ($teacher->classSubjects()->exists()) {
            $blockers[] = 'Cannot delete: this teacher has subject teaching assignments.';
        }

        $teacher->loadMissing('employee');
        if ($teacher->employee?->user_id !== null) {
            $blockers[] = 'Cannot delete: linked to a user account. Unlink it first.';
        }

        return $blockers;
    }
}
