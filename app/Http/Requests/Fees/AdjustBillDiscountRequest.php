<?php

namespace App\Http\Requests\Fees;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AdjustBillDiscountRequest extends FormRequest
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
            'discount_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
