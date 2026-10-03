<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSchoolProfileRequest;
use App\Services\Audit\AuditLogger;
use App\Services\SchoolSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SchoolProfileController extends Controller
{
    public function __construct(
        private readonly SchoolSettings $schoolSettings,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Show school profile settings.
     */
    public function edit(): Response
    {
        $setting = $this->schoolSettings->row();

        return Inertia::render('settings/school', [
            'profile' => [
                'school_name' => $setting->school_name,
                'npsn' => $setting->npsn,
                'school_address' => $setting->school_address,
                'school_phone' => $setting->school_phone,
                'school_email' => $setting->school_email,
                'has_logo' => $setting->logo_path !== null,
                'principal_name' => $setting->principal_name,
                'principal_nip' => $setting->principal_nip,
                'bank_name' => $setting->bank_name,
                'bank_account_number' => $setting->bank_account_number,
                'bank_account_holder' => $setting->bank_account_holder,
                'default_curriculum' => $setting->default_curriculum,
                'default_passing_threshold' => (string) ($setting->default_passing_threshold ?? '75.00'),
            ],
        ]);
    }

    /**
     * Update school profile settings.
     */
    public function update(UpdateSchoolProfileRequest $request): RedirectResponse
    {
        $setting = $this->schoolSettings->row();
        $validated = $request->validated();

        $logoFile = $request->file('logo');
        unset($validated['logo']);

        DB::transaction(function () use ($setting, $validated, $logoFile) {
            $oldValues = $setting->only(array_keys($validated));

            if ($logoFile !== null) {
                // Delete previous logo if exists
                if ($setting->logo_path && Storage::disk('local')->exists($setting->logo_path)) {
                    Storage::disk('local')->delete($setting->logo_path);
                }
                $validated['logo_path'] = Storage::disk('local')->putFile('school', $logoFile);
                $oldValues['logo_path'] = $setting->logo_path;
            }

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

        $this->schoolSettings->refresh();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'School profile updated.']);

        return to_route('school-profile.edit');
    }
}
