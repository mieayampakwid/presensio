<?php

namespace App\Http\Controllers\AcademicYears;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYears\UpdateSemestersRequest;
use App\Models\AcademicYear;
use App\Services\AcademicYears\SemesterService;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SemesterController extends Controller
{
    public function __construct(
        private readonly SemesterService $semesterService,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Show semester boundaries for an academic year.
     */
    public function edit(AcademicYear $academicYear): Response
    {
        $semesters = $academicYear->semesters()->orderBy('number')->get();

        return Inertia::render('academic-years/semesters', [
            'academic_year' => [
                'id' => $academicYear->id,
                'name' => $academicYear->name,
                'starts_at' => $academicYear->starts_at->toDateString(),
                'ends_at' => $academicYear->ends_at->toDateString(),
                'is_active' => $academicYear->is_active,
            ],
            'semesters' => $semesters->map(fn ($s) => [
                'id' => $s->id,
                'number' => $s->number,
                'name' => $s->name,
                'starts_at' => $s->starts_at->toDateString(),
                'ends_at' => $s->ends_at->toDateString(),
            ]),
        ]);
    }

    /**
     * Update semester boundaries for an academic year.
     */
    public function update(UpdateSemestersRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $request->validated();
        $oldSemesters = $academicYear->semesters()->orderBy('number')->get()->map(fn ($s) => [
            'number' => $s->number,
            'starts_at' => $s->starts_at->toDateString(),
            'ends_at' => $s->ends_at->toDateString(),
        ])->all();

        DB::transaction(function () use ($academicYear, $validated, $oldSemesters) {
            $this->semesterService->updateBoundaries($academicYear, $validated['semesters']);

            $this->auditLogger->record(
                auditable: $academicYear,
                action: 'updated',
                old: ['semesters' => $oldSemesters],
                new: ['semesters' => $validated['semesters']],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semester boundaries updated.']);

        return to_route('academic-years.semesters.edit', $academicYear);
    }
}
