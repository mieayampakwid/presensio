<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\Attendance\ClassAccess;
use App\Services\Reports\AttendanceReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Class attendance report (spec 06 §Requirements 2): per-student counts
 * + rate and the students × dates grid. Admin/teacher only, scoped
 * through ClassAccess like the exception dashboard (spec 03 §4).
 */
class ClassReportController extends Controller
{
    public function __construct(private readonly AttendanceReportService $reports) {}

    public function index(Request $request): Response
    {
        $classIds = ClassAccess::classIds($request->user());

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $requestedClassId = $request->integer('class_id');
        $classId = $classIds->contains($requestedClassId)
            ? $requestedClassId
            : (int) $classes->first()?->id;

        [$from, $to] = $this->reports->resolveRange($request->string('from')->toString(), $request->string('to')->toString());
        $includeExcused = $request->boolean('include_excused');

        $class = $classId !== 0 ? SchoolClass::query()->find($classId) : null;

        return Inertia::render('reports/class-report', [
            'classes' => $classes,
            'filters' => [
                'class_id' => $classId !== 0 ? $classId : null,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'include_excused' => $includeExcused,
            ],
            'report' => $class === null
                ? null
                : $this->reports->classReport($class, $from, $to, $includeExcused),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $class = SchoolClass::query()->find($request->integer('class_id'));

        abort_if($class === null, 404);
        abort_unless(ClassAccess::canAccess($request->user(), $class->id), 403);

        [$from, $to] = $this->reports->resolveRange($request->string('from')->toString(), $request->string('to')->toString());

        return $this->reports->classCsv($class, $from, $to, $request->boolean('include_excused'));
    }
}
