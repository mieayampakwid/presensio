<?php

namespace App\Http\Controllers\Classes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\StoreSchoolClassRequest;
use App\Http\Requests\Classes\UpdateSchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    /**
     * Display a listing of the classes.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();

        $classes = SchoolClass::query()
            ->with('teacher:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('teacher', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('classes/index', [
            'classes' => $classes,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new class.
     */
    public function create(): Response
    {
        return Inertia::render('classes/create', [
            'teachers' => $this->teacherOptions(),
        ]);
    }

    /**
     * Store a newly created class.
     */
    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        SchoolClass::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Class created.']);

        return to_route('classes.index');
    }

    /**
     * Show the form for editing the specified class.
     */
    public function edit(SchoolClass $schoolClass): Response
    {
        return Inertia::render('classes/edit', [
            'class' => $schoolClass,
            'teachers' => $this->teacherOptions(),
        ]);
    }

    /**
     * Update the specified class.
     */
    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Class updated.']);

        return to_route('classes.edit', $schoolClass);
    }

    /**
     * Remove the specified class.
     */
    public function destroy(SchoolClass $schoolClass): RedirectResponse
    {
        $blockers = $this->deletionBlockers($schoolClass);

        if ($blockers !== []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $blockers[0]]);

            return back();
        }

        $schoolClass->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Class deleted.']);

        return to_route('classes.index');
    }

    /**
     * Reasons the class cannot be deleted, in display order. Attendance rows
     * carry no class_id (spec 03 schema) and hang off students, so deleting
     * a class never orphans attendance history — no extra blocker is needed
     * here. Do not invent an attendances.class_id column to add one.
     *
     * @return list<string>
     */
    private function deletionBlockers(SchoolClass $schoolClass): array
    {
        $blockers = [];

        if ($schoolClass->students()->exists()) {
            $blockers[] = 'Cannot delete: students are still enrolled in this class.';
        }

        return $blockers;
    }

    /**
     * Homeroom teacher options for the form select (serializes to
     * `{id, name}` rows for the frontend).
     *
     * @return Collection<int, Teacher>
     */
    private function teacherOptions()
    {
        return Teacher::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
