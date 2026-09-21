<?php

namespace App\Http\Controllers\Classes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classes\StoreSchoolClassRequest;
use App\Http\Requests\Classes\UpdateSchoolClassRequest;
use App\Models\AcademicYear;
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
     * Display a listing of the classes, per academic year (default: the
     * active one — spec 07).
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $years = AcademicYear::query()->orderByDesc('starts_at')->get(['id', 'name', 'is_active']);

        $yearId = $this->resolveYearId($request, $years);

        $classes = SchoolClass::query()
            ->where('academic_year_id', $yearId)
            ->with('teacher:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('teacher', function (Builder $query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('classes/index', [
            'classes' => $classes,
            'years' => $years,
            'filters' => [
                'search' => $search,
                'year_id' => $yearId,
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
     * Store a newly created class, in the active year (spec 07 — past
     * years are immutable history; new classes arrive via roll-over).
     */
    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        SchoolClass::create([
            ...$request->validated(),
            'academic_year_id' => AcademicYear::active()?->id,
        ]);

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
     * Reasons the class cannot be deleted, in display order. Enrollment
     * history is the class's permanent record (spec 02 v2.0) — any
     * enrollment ever referencing it blocks deletion, whether or not
     * students are still enrolled today.
     *
     * @return list<string>
     */
    private function deletionBlockers(SchoolClass $schoolClass): array
    {
        $blockers = [];

        if ($schoolClass->enrollments()->exists()) {
            $blockers[] = 'Cannot delete: students have enrollment history in this class.';
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

    /**
     * The selected year, defaulting to the active one; an unknown year
     * falls back to it too.
     *
     * @param  Collection<int, AcademicYear>  $years
     */
    private function resolveYearId(Request $request, Collection $years): ?int
    {
        $requested = $request->integer('year_id');

        if ($requested !== 0 && $years->contains('id', $requested)) {
            return $requested;
        }

        return $years->firstWhere('is_active', true)?->id;
    }
}
