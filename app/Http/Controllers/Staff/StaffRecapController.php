<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Services\SchoolSettings;
use App\Services\Staff\StaffRecapService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffRecapController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly StaffRecapService $recap,
    ) {}

    /**
     * Monthly recap over all active employees (spec 16 §Req 6).
     */
    public function index(Request $request): Response
    {
        $recap = $this->recap->monthlyRecap($this->resolveMonth($request));

        return Inertia::render('staff/recap', [
            'month' => $recap['month'],
            'start_date' => $recap['start_date'],
            'end_date' => $recap['end_date'],
            'rows' => $recap['rows'],
            'summary' => $recap['summary'],
        ]);
    }

    /**
     * CSV download mirroring the recap screen.
     */
    public function export(Request $request): StreamedResponse
    {
        return $this->recap->exportCsv($this->resolveMonth($request));
    }

    /**
     * The requested Y-m month, or the current month in the school
     * timezone when missing or malformed.
     */
    private function resolveMonth(Request $request): string
    {
        $month = $request->string('month')->toString();

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1) {
            return $month;
        }

        return $this->settings->now()->format('Y-m');
    }
}
