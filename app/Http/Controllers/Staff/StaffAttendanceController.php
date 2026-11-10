<?php

namespace App\Http\Controllers\Staff;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\UpsertStaffAttendanceRequest;
use App\Services\SchoolSettings;
use App\Services\Staff\StaffAttendanceOverrideService;
use App\Services\Staff\StaffAttendanceSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly StaffAttendanceSummaryService $summaryService,
        private readonly StaffAttendanceOverrideService $overrideService,
    ) {}

    /**
     * Daily overview of staff attendance (spec 16 §Req 3).
     */
    public function index(Request $request): Response
    {
        $todayDate = $this->settings->todayDate();
        $date = $this->resolveDate($request, $todayDate);
        $filter = $request->string('filter')->toString() ?: 'all';

        $overview = $this->summaryService->getDailyOverview($date, $filter);

        return Inertia::render('staff/attendance', array_merge($overview, [
            'can_override' => $request->user()?->hasRole(UserRole::Admin) ?? false,
        ]));
    }

    /**
     * Manual override / upsert for staff attendance (spec 16 §Req 3).
     */
    public function upsert(UpsertStaffAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $date = $validated['date'];
        $shift = $request->effectiveShift();

        $this->overrideService->override(
            validated: $validated,
            shift: $shift,
            overrideByUserId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('toasts.staff.attendance_updated')]);

        return to_route('staff-attendance.index', ['date' => $date]);
    }

    /**
     * The requested Y-m-d date, or today when missing or malformed.
     */
    private function resolveDate(Request $request, string $todayDate): string
    {
        $value = $request->string('date')->toString();

        if ($value !== '' && Date::hasFormat($value, 'Y-m-d')) {
            return Date::parse($value)->toDateString();
        }

        return $todayDate;
    }
}
