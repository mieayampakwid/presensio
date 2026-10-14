<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateReportCardEntryRequest;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use App\Services\ReportCards\ReportCardWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportCardWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReportCardWorkspaceService $workspace,
    ) {}

    /**
     * Landing for the sidebar: the first class the user can view report
     * cards for, alphabetically.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $class = SchoolClass::query()
            ->whereIn('id', ClassAccess::academicClassIds($user))
            ->orderBy('name')
            ->first();

        abort_if($class === null, 404, 'Tidak ada kelas yang dapat dilihat.');

        return redirect()->route('classes.report-cards.show', ['school_class' => $class->id]);
    }

    /**
     * Homeroom workspace: finalization checklist and per-student entries.
     */
    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::academicClassIds($user)->contains($schoolClass->id),
            403,
        );

        $semester = $this->resolveSemester($request, $schoolClass);

        return Inertia::render('teacher/report-cards', [
            'school_class' => ['id' => $schoolClass->id, 'name' => $schoolClass->name, 'grade_level' => $schoolClass->grade_level],
            'semester' => ['id' => $semester->id, 'name' => $semester->name, 'number' => $semester->number],
            'semesters' => $schoolClass->academicYear->semesters
                ->map(fn (Semester $s) => ['id' => $s->id, 'name' => $s->name, 'number' => $s->number])
                ->all(),
            'checklist' => $this->workspace->checklist($schoolClass, $semester),
            'students' => $this->workspace->students($schoolClass, $semester),
            'can' => [
                'edit' => $user !== null && ClassAccess::canWrite($user, $schoolClass->id),
            ],
        ]);
    }

    /**
     * Save the homeroom entry (notes and, in semester 2, the decision).
     */
    public function updateEntry(UpdateReportCardEntryRequest $request, SchoolClass $schoolClass, Student $student): RedirectResponse
    {
        /** @var Semester $semester */
        $semester = Semester::query()->findOrFail((int) $request->validated('semester_id'));

        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, 'Siswa tidak terdaftar di kelas ini.');

        $this->workspace->saveEntry($schoolClass, $semester, $student, $request->validated());

        return back();
    }

    /**
     * The would-be snapshot of one student; nothing is persisted.
     */
    public function preview(Request $request, SchoolClass $schoolClass, Student $student): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::academicClassIds($user)->contains($schoolClass->id),
            403,
        );

        $semester = $this->resolveSemester($request, $schoolClass);

        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, 'Siswa tidak terdaftar di kelas ini.');

        return response()->json($this->workspace->preview($schoolClass, $semester, $student));
    }

    /**
     * The semester must belong to the class's academic year — no cross-year
     * fallback; defaults to the first semester of the class's year.
     */
    private function resolveSemester(Request $request, SchoolClass $schoolClass): Semester
    {
        $schoolClass->loadMissing('academicYear.semesters');
        $semesters = $schoolClass->academicYear->semesters;

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;

        /** @var Semester|null $semester */
        $semester = $semesters->firstWhere('id', $semesterId) ?? $semesters->first();
        abort_if($semester === null, 404, 'Semester tidak ditemukan.');

        return $semester;
    }
}
