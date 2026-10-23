<?php

namespace App\Http\Requests\Fees;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class CancelOverpaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(UserRole::Admin, UserRole::Finance) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }
}
