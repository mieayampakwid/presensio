<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anchors every piece of scan + sweep logic; the scheduler and the scan
 * service re-read these live, so changes apply within a minute.
 */
class UpdateAttendanceSettingsRequest extends FormRequest
{
    /**
     * Curated list — zones the school actually spans, kept explicit so the
     * native <select> stays predictable.
     */
    private const TIMEZONES = [
        'Asia/Jakarta',
        'Asia/Makassar',
        'Asia/Jayapura',
        'Asia/Singapore',
        'Asia/Tokyo',
        'UTC',
    ];

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'school_timezone' => ['required', Rule::in(self::TIMEZONES)],
            'school_start_time' => ['required', 'date_format:H:i'],
            'auto_absent_cron_time' => ['required', 'date_format:H:i'],
            'require_checkout' => ['required', 'boolean'],
            'scan_debounce_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'scan_drift_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }
}
