<?php

namespace App\Http\Requests\Parent;

use App\Enums\UserRole;
use App\Models\Bill;
use App\Services\SchoolSettings;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTransferRequest extends FormRequest
{
    /**
     * A guardian may only pay bills of their own linked students (AC-14-12).
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasAnyRole(UserRole::Parent, UserRole::Admin)) {
            return false;
        }

        $billIds = collect((array) $this->input('allocations'))
            ->map(fn ($row) => is_array($row) ? (int) ($row['bill_id'] ?? 0) : 0)
            ->unique()->values();

        if ($billIds->isEmpty()) {
            return true;
        }

        if ($user->guardian === null) {
            return false;
        }

        return Bill::query()
            ->whereIn('id', $billIds)
            ->whereIn('student_id', $user->guardian->students()->select('students.id'))
            ->count() === $billIds->count();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.bill_id' => ['required', 'integer', 'distinct', 'exists:bills,id'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.app(SchoolSettings::class)->todayDate()],
            'origin_bank' => ['required', 'string', 'max:100'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The transferred amount must equal the allocated total, and the school must have a destination account.
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

                if (Money::cmp($allocated, $this->input('amount')) !== 0) {
                    $validator->errors()->add('amount', 'Total alokasi harus sama dengan jumlah transfer.');
                }

                if (app(SchoolSettings::class)->defaultTransferAccountId() === null) {
                    $validator->errors()->add('transfer', 'Rekening tujuan transfer belum diatur oleh sekolah.');
                }
            },
        ];
    }
}
