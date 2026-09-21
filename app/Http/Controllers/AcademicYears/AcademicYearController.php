<?php

namespace App\Http\Controllers\AcademicYears;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYears\RollOverRequest;
use App\Http\Requests\AcademicYears\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYears\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\AcademicYears\RollOverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Admin CRUD for academic years plus the annual roll-over use case
 * (spec 07). Activating a year is exclusive; roll-over promotes the
 * active year's rosters into the target year and flips the active flag
 * inside one transaction.
 */
class AcademicYearController extends Controller
{
    public function __construct(private readonly RollOverService $rollOverService) {}

    public function index(): Response
    {
        $years = AcademicYear::query()
            ->withCount('classes')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('academic-years/index', [
            'years' => $years,
        ]);
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        AcademicYear::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academic year created.']);

        return to_route('academic-years.index');
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academic year updated.']);

        return to_route('academic-years.index');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        DB::transaction(function () use ($academicYear): void {
            AcademicYear::query()->whereKeyNot($academicYear->id)->update(['is_active' => false]);
            $academicYear->forceFill(['is_active' => true])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$academicYear->name} is now the active year."]);

        return to_route('academic-years.index');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->classes()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cannot delete: classes exist in this year.']);

            return back();
        }

        $academicYear->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Academic year deleted.']);

        return to_route('academic-years.index');
    }

    /**
     * The roll-over mapping screen: source = the active year, target
     * chosen (or created) via the `target_year_id` query param.
     */
    public function rollOver(Request $request): Response
    {
        $source = AcademicYear::active();

        if ($source === null) {
            return Inertia::render('academic-years/roll-over', [
                'source' => null,
                'target_years' => [],
                'target_classes' => [],
                'source_classes' => [],
                'teachers' => [],
            ]);
        }

        $targetYears = AcademicYear::query()
            ->whereKeyNot($source->id)
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'starts_at', 'ends_at']);

        $targetYearId = (int) $request->query('target_year_id', (string) $targetYears->first()?->id);
        $target = $targetYears->firstWhere('id', $targetYearId);

        $sourceClasses = SchoolClass::query()
            ->where('academic_year_id', $source->id)
            ->withCount(['students' => fn ($query) => $query->whereNull('enrollments.ended_on')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $alreadyPromoted = Enrollment::query()
            ->whereHas('schoolClass', fn ($query) => $query->where('academic_year_id', $target?->id))
            ->exists();

        return Inertia::render('academic-years/roll-over', [
            'source' => ['id' => $source->id, 'name' => $source->name],
            'target_years' => $targetYears,
            'target_year_id' => $target?->id,
            'target_classes' => $target === null ? [] : SchoolClass::query()
                ->where('academic_year_id', $target->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'source_classes' => $sourceClasses,
            'teachers' => Teacher::query()->orderBy('name')->get(['id', 'name']),
            'already_promoted' => $alreadyPromoted,
        ]);
    }

    public function applyRollOver(RollOverRequest $request): RedirectResponse
    {
        $target = AcademicYear::query()->findOrFail($request->integer('target_year_id'));

        try {
            $this->rollOverService->rollOver($target, $request->mappings(), $request->effectiveOn());
        } catch (InvalidArgumentException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Rolled over to {$target->name} — it is now the active year."]);

        return to_route('academic-years.index');
    }
}
