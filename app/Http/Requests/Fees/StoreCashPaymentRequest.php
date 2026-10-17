<?php

namespace App\Http\Requests\Fees;

use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCashPaymentRequest extends FormRequest
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
            'amount_received' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'integer', 'distinct'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'deposit_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_cash', true)->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The received amount must equal the allocated total (AC-14-05).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $allocated = array_reduce($this->input('allocations'), fn (string $sum, array $row) => Money::add($sum, $row['amount']), '0.00');

                if (Money::cmp($allocated, $this->input('amount_received')) !== 0) {
                    $validator->errors()->add('amount_received', 'Total alokasi harus sama dengan jumlah yang diterima.');
                }
            },
        ];
    }
}
