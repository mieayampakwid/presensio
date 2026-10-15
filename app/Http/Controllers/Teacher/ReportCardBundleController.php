<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\BundleReportCardRequest;
use App\Jobs\BuildClassReportCardPdf;
use App\Models\ReportCardPublication;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Services\Attendance\ClassAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReportCardBundleController extends Controller
{
    /**
     * Queue the combined PDF build for the class's current publication.
     */
    public function store(BundleReportCardRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        // POST carries the period in the body (validated by the request).
        $semester = Semester::query()
            ->whereKey((int) $request->validated('semester_id'))
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->firstOrFail();

        $publication = $this->currentPublication($schoolClass, $semester);

        if ($publication === null) {
            throw ValidationException::withMessages([
                'publication' => ['Rapor belum diterbitkan untuk periode ini.'],
            ]);
        }

        BuildClassReportCardPdf::dispatch($schoolClass, $semester, $publication->id);

        return response()->json(['status' => 'queued', 'version' => $publication->version], 202);
    }

    /**
     * Download the built bundle of the class's current publication.
     */
    public function download(Request $request, SchoolClass $schoolClass): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ClassAccess::canWrite($user, $schoolClass->id), 403);

        // GET carries the period as the canonical ?semester= query, scoped to
        // the class's academic year.
        /** @var Semester|null $semester */
        $semester = Semester::query()
            ->whereKey((int) $request->query('semester'))
            ->where('academic_year_id', $schoolClass->academic_year_id)
            ->first();
        abort_if($semester === null, 404, 'Rapor belum diterbitkan untuk periode ini.');

        $publication = $this->currentPublication($schoolClass, $semester);
        abort_if($publication === null, 404, 'Rapor belum diterbitkan untuk periode ini.');

        $path = BuildClassReportCardPdf::path($publication);
        abort_unless(Storage::disk('local')->exists($path), 404, 'Berkas cetak semua belum siap.');

        return response((string) Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rapor-'.$schoolClass->name.'-v'.$publication->version.'.pdf"',
        ]);
    }

    private function currentPublication(SchoolClass $schoolClass, Semester $semester): ?ReportCardPublication
    {
        return ReportCardPublication::query()
            ->where('class_id', $schoolClass->id)
            ->where('semester_id', $semester->id)
            ->current()
            ->first();
    }
}
