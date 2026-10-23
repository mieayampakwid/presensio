<?php

namespace App\Http\Requests\Fees;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RefundOverpaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(UserRole::Admin, UserRole::Finance) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'refund_date' => ['required', 'date_format:Y-m-d'],
            'refund_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'bank_fee' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'bank_reference' => ['required', 'string', 'max:255'],
            'net_refund' => ['sometimes', 'required', 'numeric', 'decimal:0,2'],
            'bank_total' => ['sometimes', 'required', 'numeric', 'decimal:0,2'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
