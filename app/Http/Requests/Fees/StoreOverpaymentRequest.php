<?php

namespace App\Http\Requests\Fees;

use App\Enums\OverpaymentReason;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOverpaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(UserRole::Admin, UserRole::Finance) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'integer', 'exists:guardians,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id'],
            'related_payment_id' => ['nullable', 'integer', 'exists:payments,id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'bank_mutation_date' => ['required', 'date_format:Y-m-d'],
            'posting_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:bank_mutation_date'],
            'deposit_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'reason' => ['required', Rule::enum(OverpaymentReason::class)],
            'note' => ['nullable', 'string', 'max:5000'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
