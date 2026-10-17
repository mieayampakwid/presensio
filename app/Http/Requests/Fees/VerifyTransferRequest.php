<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'deposit_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_cash', true)->where('is_active', true)->where('is_postable', true)],
            'allocations' => ['nullable', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
        ];
    }
}
