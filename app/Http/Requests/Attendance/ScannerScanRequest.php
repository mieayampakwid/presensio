<?php

namespace App\Http\Requests\Attendance;

use App\Enums\Ability;
use Illuminate\Foundation\Http\FormRequest;

class ScannerScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Ability::OperateScanner->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['credential' => ['required', 'string', 'max:255']];
    }
}
