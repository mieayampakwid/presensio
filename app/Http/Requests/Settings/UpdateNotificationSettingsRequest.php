<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'notification_channels' => ['nullable', 'array'],
            'whatsapp_daily_quota' => ['nullable', 'integer', 'min:1'],
            'quiet_hours_start' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'quiet_hours_end' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'bill_reminder_days_before' => ['required', 'integer', 'min:1', 'max:30'],
        ];
    }
}
