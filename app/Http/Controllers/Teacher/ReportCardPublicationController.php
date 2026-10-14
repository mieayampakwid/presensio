<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\PublishReportCardRequest;
use App\Http\Requests\Teacher\RetractReportCardRequest;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Services\ReportCards\ReportCardPublisher;
use Illuminate\Http\JsonResponse;

class ReportCardPublicationController extends Controller
{
    public function __construct(
        private readonly ReportCardPublisher $publisher,
    ) {}

    /**
     * Publish the class's report cards for the semester as one immutable
     * version.
     */
    public function publish(PublishReportCardRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        /** @var Semester $semester */
        $semester = Semester::query()->findOrFail((int) $request->validated('semester_id'));

        $result = $this->publisher->publish($schoolClass, $semester);

        return response()->json($result);
    }

    /**
     * Retract the current version with a required reason.
     */
    public function retract(RetractReportCardRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        /** @var Semester $semester */
        $semester = Semester::query()->findOrFail((int) $request->validated('semester_id'));

        $this->publisher->retract($schoolClass, $semester, (string) $request->validated('reason'));

        return response()->json(['retracted' => true]);
    }
}
