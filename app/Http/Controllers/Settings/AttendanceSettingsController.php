<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAttendanceSettingsRequest;
use App\Models\SchoolSetting;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function __construct(private readonly SchoolSettings $settings) {}

    public function edit(): Response
    {
        $row = $this->settings->row();

        return Inertia::render('settings/attendance', [
            'settings' => [
                'school_timezone' => $row->school_timezone,
                'school_start_time' => substr((string) $row->school_start_time, 0, 5),
                'auto_absent_cron_time' => substr((string) $row->auto_absent_cron_time, 0, 5),
                'require_checkout' => $row->require_checkout,
                'scan_debounce_minutes' => $row->scan_debounce_minutes,
                'scan_drift_tolerance_minutes' => $row->scan_drift_tolerance_minutes,
            ],
        ]);
    }

    public function update(UpdateAttendanceSettingsRequest $request): RedirectResponse
    {
        SchoolSetting::query()->firstOrFail()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance settings updated.']);

        return to_route('attendance-settings.edit');
    }
}
