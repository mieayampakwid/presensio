<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ReportCards\ReportCardArchiveService;
use App\Services\ReportCards\ReportCardPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ReportCardController extends Controller
{
    private const NOT_PUBLISHED_MESSAGE = 'Rapor belum diterbitkan untuk periode ini.';

    public function __construct(
        private readonly ReportCardArchiveService $archive,
        private readonly ReportCardPdf $pdf,
    ) {}

    /**
     * The student's own archive: only semesters with a current publication.
     */
    public function index(Request $request): InertiaResponse
    {
        $student = $this->ownStudent();

        $cards = $this->archive->currentCards($student);
        $semesterId = $request->filled('semester') ? (int) $request->input('semester') : null;
        $selected = $this->archive->selected($cards, $semesterId);
        abort_if($selected === null, 403, self::NOT_PUBLISHED_MESSAGE);

        return Inertia::render('student/report-cards', [
            'semesters' => $this->archive->summaries($cards),
            'selected_semester_id' => $selected->publication->semester_id,
            'payload' => $this->archive->payload($selected),
        ]);
    }

    /**
     * The stored snapshot of the current publication as a downloadable PDF.
     */
    public function pdf(Request $request, Semester $semester): Response
    {
        $student = $this->ownStudent();

        $card = $this->archive->selected($this->archive->currentCards($student), $semester->id);
        abort_if($card === null, 403, self::NOT_PUBLISHED_MESSAGE);

        $bytes = $this->pdf->pdf($card->payload, $card->curriculum->value);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapor-'.str_replace(' ', '-', $student->full_name).'.pdf"',
        ]);
    }

    /**
     * The student profile linked to the acting user; role:student visitors
     * without a profile see nothing.
     */
    private function ownStudent(): Student
    {
        $user = request()->user();
        abort_if($user === null, 403);

        /** @var Student|null $student */
        $student = Student::query()->where('user_id', $user->id)->first();
        abort_if($student === null, 403, self::NOT_PUBLISHED_MESSAGE);

        return $student;
    }
}
