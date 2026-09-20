<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Attendance\ClassAccess;
use App\Services\Reports\AttendanceReportService;
use App\Services\SchoolSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Live presence board (spec 06 §Requirements 6): a read-only view over
 * today's records — polls nothing, triggers nothing, notifies nobody.
 */
class PresenceBoardController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly SchoolSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('reports/presence-board', [
            'board' => $this->reports->board(ClassAccess::classIds($request->user()), $request->user()),
            // Per-day flag — changes only at school midnight, so it stays
            // outside the polled `board` prop.
            'is_school_day' => $this->settings->isSchoolDay($this->settings->todayDate()),
        ]);
    }
}
