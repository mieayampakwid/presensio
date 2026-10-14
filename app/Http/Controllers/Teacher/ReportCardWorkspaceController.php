<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Curriculum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateReportCardEntryRequest;
use App\Jobs\BuildClassReportCardPdf;
use App\Models\ReportCard;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use App\Services\ReportCards\ReportCardPdf;
use App\Services\ReportCards\ReportCardPublisher;
use App\Services\ReportCards\ReportCardWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportCardWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReportCardWorkspaceService $workspace,
        private readonly ReportCardPublisher $publisher,
        private readonly ReportCardPdf $pdf,
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
    public function show(Request $request, SchoolClass $schoolClass): InertiaResponse
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
            'publication' => $this->publisher->currentPublication($schoolClass, $semester),
            'bundle_available' => $this->bundleAvailable($schoolClass, $semester),
            'can' => [
                'edit' => ClassAccess::canWrite($user, $schoolClass->id),
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
     * Stream the stored snapshot of the current publication as a PDF.
     * Display fields come from the stored payload only; class/semester data
     * is used for authorization and record selection, never the live as-of
     * roster (a later transfer must not change availability of an issued
     * report card).
     */
    public function pdf(Request $request, SchoolClass $schoolClass, Student $student): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::academicClassIds($user)->contains($schoolClass->id),
            403,
        );

        $semester = $this->resolveSemester($request, $schoolClass);

        /** @var ReportCardPublication|null $publication */
        $publication = ReportCardPublication::query()
            ->where('class_id', $schoolClass->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();
        abort_if($publication === null, 403, 'Rapor belum diterbitkan untuk periode ini.');

        /** @var ReportCard $card */
        $card = $publication->reportCards()
            ->where('student_id', $student->id)
            ->firstOrFail();

        return $this->pdfResponse(
            $this->pdf->pdf($card->payload, $card->curriculum->value),
            sprintf('rapor-%s-%s-v%d.pdf', $schoolClass->name, str_replace(' ', '-', $student->full_name), $publication->version),
            'attachment',
        );
    }

    /**
     * Stream the would-be (non-persisted) snapshot as an inline PDF preview.
     */
    public function previewPdf(Request $request, SchoolClass $schoolClass, Student $student): Response
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ClassAccess::academicClassIds($user)->contains($schoolClass->id),
            403,
        );

        $semester = $this->resolveSemester($request, $schoolClass);
        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, 'Siswa tidak terdaftar di kelas ini.');

        $payload = $this->workspace->preview($schoolClass, $semester, $student);
        $curriculum = $schoolClass->curriculum instanceof Curriculum
            ? $schoolClass->curriculum
            : Curriculum::from((string) $schoolClass->curriculum);

        return $this->pdfResponse(
            $this->pdf->pdf($payload, $curriculum->value),
            sprintf('pratinjau-rapor-%s-%s.pdf', $schoolClass->name, str_replace(' ', '-', $student->full_name)),
            'inline',
        );
    }

    /**
     * Whether a combined PDF exists for the class's current publication.
     */
    private function bundleAvailable(SchoolClass $schoolClass, Semester $semester): bool
    {
        $publication = ReportCardPublication::query()
            ->where('class_id', $schoolClass->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();

        return $publication !== null
            && Storage::disk('local')->exists(BuildClassReportCardPdf::path($publication));
    }

    private function pdfResponse(string $bytes, string $filename, string $disposition): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
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
