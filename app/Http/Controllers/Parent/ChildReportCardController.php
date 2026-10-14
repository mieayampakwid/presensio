<?php

namespace App\Http\Controllers\Parent;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ReportCard;
use App\Models\ReportCardPublication;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Services\ReportCards\ReportCardArchiveService;
use App\Services\ReportCards\ReportCardPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ChildReportCardController extends Controller
{
    private const NOT_PUBLISHED_MESSAGE = 'Rapor belum diterbitkan untuk periode ini.';

    public function __construct(
        private readonly ReportCardArchiveService $archive,
        private readonly ReportCardPdf $pdf,
    ) {}

    /**
     * Sidebar landing: the linked child with a current publication, else the
     * first linked child — never a hardcoded id, and a guardian is not
     * stranded on a child without reports when a sibling has them.
     */
    public function landing(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $children = $user->hasRole(UserRole::Admin)
            ? Student::query()->orderBy('full_name')->get()
            : $user->guardian?->students()->orderBy('full_name')->get() ?? collect();

        $student = $children
            ->first(fn (Student $student) => ReportCardPublication::query()
                ->current()
                ->whereHas('reportCards', fn ($query) => $query->where('student_id', $student->id))
                ->exists())
            ?? $children->first();

        abort_if($student === null, 404, 'Belum ada anak yang terhubung.');

        return redirect()->route('children.report-cards.index', $student);
    }

    /**
     * The guardian's archive for one child: only semesters with a current
     * publication; a requested period without one is 403 even when other
     * semesters are published.
     */
    public function index(Request $request, Student $student): InertiaResponse
    {
        $this->authorizeGuardian($request->user(), $student);

        $cards = $this->archive->currentCards($student);
        $selected = $this->archive->selected($cards, $this->requestedSemesterId($request));
        abort_if($selected === null, 403, self::NOT_PUBLISHED_MESSAGE);

        return Inertia::render('parent/child-report-cards', [
            'child' => ['id' => $student->id, 'name' => $student->full_name],
            'students' => $this->selectableChildren($request->user()),
            'semesters' => $this->archive->summaries($cards),
            'selected_semester_id' => $selected->publication->semester_id,
            'payload' => $this->archive->payload($selected),
        ]);
    }

    /**
     * The stored snapshot of the current publication as a downloadable PDF.
     */
    public function pdf(Request $request, Student $student, Semester $semester): Response
    {
        $this->authorizeGuardian($request->user(), $student);

        $cards = $this->archive->currentCards($student);
        $card = $this->archive->selected($cards, $semester->id);
        abort_if($card === null, 403, self::NOT_PUBLISHED_MESSAGE);

        return $this->pdfResponse($card, $student);
    }

    private function authorizeGuardian(?User $user, Student $student): void
    {
        abort_if($user === null, 403);

        if ($user->hasRole(UserRole::Admin)) {
            return;
        }

        $guardian = $user->guardian;
        abort_unless(
            $guardian !== null && $guardian->students()->where('students.id', $student->id)->exists(),
            403,
        );
    }

    private function requestedSemesterId(Request $request): ?int
    {
        return $request->filled('semester') ? (int) $request->input('semester') : null;
    }

    /**
     * Linked children for the page's child selector.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function selectableChildren(?User $user): array
    {
        $guardian = $user?->guardian;
        if ($guardian === null) {
            return [];
        }

        return $guardian->students()
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $student) => ['id' => $student->id, 'name' => $student->full_name])
            ->all();
    }

    private function pdfResponse(ReportCard $card, Student $student): Response
    {
        $bytes = $this->pdf->pdf($card->payload, $card->curriculum->value);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapor-'.str_replace(' ', '-', $student->full_name).'.pdf"',
        ]);
    }
}
