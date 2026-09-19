<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\StoreTeacherRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Models\Teacher;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('teacher_number', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
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
        Teacher::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teacher created.']);

        return to_route('teachers.index');
    }

    /**
     * Show the form for editing the specified teacher.
     */
    public function edit(Teacher $teacher): Response
    {
        return Inertia::render('teachers/edit', [
            'teacher' => $teacher,
        ]);
    }

    /**
     * Update the specified teacher.
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $teacher->update($request->validated());

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

        $teacher->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teacher deleted.']);

        return to_route('teachers.index');
    }

    /**
     * Reasons the teacher cannot be deleted, in display order. Spec 03
     * appends its attendance-history check here.
     *
     * @return list<string>
     */
    private function deletionBlockers(Teacher $teacher): array
    {
        $blockers = [];

        if ($teacher->classes()->exists()) {
            $blockers[] = 'Cannot delete: this teacher is the homeroom teacher of a class.';
        }

        if ($teacher->user_id !== null) {
            $blockers[] = 'Cannot delete: linked to a user account. Unlink it first.';
        }

        return $blockers;
    }
}
