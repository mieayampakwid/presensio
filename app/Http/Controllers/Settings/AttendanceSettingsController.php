<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateAttendanceSettingsRequest;
use App\Models\SchoolSetting;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $settings,
        private readonly AuditLogger $auditLogger,
    ) {}

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
                'school_operational_days' => $this->settings->operationalWeekdays(),
            ],
        ]);
    }

    public function update(UpdateAttendanceSettingsRequest $request): RedirectResponse
    {
        $setting = SchoolSetting::query()->firstOrFail();
        $validated = $request->validated();

        DB::transaction(function () use ($setting, $validated) {
            $changed = [];
            $oldChanged = [];
            foreach ($validated as $key => $value) {
                if ($setting->{$key} !== $value) {
                    $changed[$key] = $value;
                    $oldChanged[$key] = $setting->{$key};
                }
            }

            $setting->update($validated);

            if (! empty($changed)) {
                $this->auditLogger->record(
                    auditable: $setting,
                    action: 'updated',
                    old: $oldChanged,
                    new: $changed,
                );
            }
        });

        $this->settings->refresh();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Attendance settings updated.']);

        return to_route('attendance-settings.edit');
    }
}
