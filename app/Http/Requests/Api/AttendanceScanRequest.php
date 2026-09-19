<?php

namespace App\Http\Requests\Api;

use App\Enums\ScanMethod;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

/**
 * @property string $credential_type
 * @property string $credential
 * @property string|null $scanned_at
 */
class AttendanceScanRequest extends FormRequest
{
    /**
     * Authentication is the device key (middleware), not a user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'credential_type' => ['required', Rule::in(['rfid', 'dynamic_qr'])],
            'credential' => ['required', 'string', 'max:255'],
            'scanned_at' => ['nullable', 'date'],
        ];
    }

    public function scanMethod(): ScanMethod
    {
        return $this->string('credential_type')->toString() === 'rfid'
            ? ScanMethod::Rfid
            : ScanMethod::DynamicQr;
    }

    /**
     * The scanner's own clock reading; null when absent or unparseable.
     */
    public function deviceScannedAt(): ?CarbonInterface
    {
        if (! $this->filled('scanned_at')) {
            return null;
        }

        try {
            return Date::parse($this->string('scanned_at')->toString());
        } catch (\Throwable) {
            return null;
        }
    }
}
