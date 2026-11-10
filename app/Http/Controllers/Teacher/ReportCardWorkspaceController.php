<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\Curriculum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\UpdateReportCardEntryRequest;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Services\Attendance\ClassAccess;
use App\Services\ReportCards\ReportCardPdf;
use App\Services\ReportCards\ReportCardWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportCardWorkspaceController extends Controller
{
    public function __construct(
        private readonly ReportCardWorkspaceService $workspace,
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

        $class = $this->workspace->firstAccessibleClass($user);
        abort_if($class === null, 404, __('toasts.report_cards.no_class'));

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

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $semester = $this->workspace->resolveSemester($schoolClass, $semesterId);

        return Inertia::render(
            'teacher/report-cards',
            $this->workspace->getWorkspaceViewData($user, $schoolClass, $semester),
        );
    }

    /**
     * Save the homeroom entry (notes and, in semester 2, the decision).
     */
    public function updateEntry(UpdateReportCardEntryRequest $request, SchoolClass $schoolClass, Student $student): RedirectResponse
    {
        /** @var Semester $semester */
        $semester = Semester::query()->findOrFail((int) $request->validated('semester_id'));

        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, __('toasts.report_cards.student_not_in_roster'));

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

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $semester = $this->workspace->resolveSemester($schoolClass, $semesterId);

        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, __('toasts.report_cards.student_not_in_roster'));

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

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $semester = $this->workspace->resolveSemester($schoolClass, $semesterId);

        $record = $this->workspace->findPublishedCard($schoolClass, $semester, $student);

        return $this->pdfResponse(
            $this->pdf->pdf($record['card']->payload, $record['card']->curriculum->value),
            sprintf('rapor-%s-%s-v%d.pdf', $schoolClass->name, str_replace(' ', '-', $student->full_name), $record['publication']->version),
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

        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $semester = $this->workspace->resolveSemester($schoolClass, $semesterId);

        abort_unless($this->workspace->rosterContains($schoolClass, $semester, $student->id), 403, __('toasts.report_cards.student_not_in_roster'));

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

    private function pdfResponse(string $bytes, string $filename, string $disposition): Response
    {
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
