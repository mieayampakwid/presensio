<?php

namespace App\Http\Requests\Fees;

use App\Enums\AccountType;
use App\Enums\FeeKind;
use App\Models\FeeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class SaveFeeTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs for validation: uppercase fee type code.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->input('code'))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Exists|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var FeeType|null $feeType */
        $feeType = $this->route('fee_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', Rule::unique(FeeType::class, 'code')->ignore($feeType)],
            'type' => ['required', Rule::enum(FeeKind::class)],
            'default_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'description' => ['nullable', 'string'],
            'receivable_account_id' => ['required', 'integer', $this->postableAccount(AccountType::Asset, false)],
            'revenue_account_id' => ['required', 'integer', $this->postableAccount(AccountType::Revenue, false)],
            'discount_account_id' => ['required', 'integer', $this->postableAccount(AccountType::Revenue, true)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receivable_account_id.exists' => 'Akun piutang harus akun Aset yang aktif dan postable.',
            'revenue_account_id.exists' => 'Akun pendapatan harus akun Pendapatan (non-kontra) yang aktif dan postable.',
            'discount_account_id.exists' => 'Akun diskon harus akun kontra Pendapatan yang aktif dan postable.',
        ];
    }

    private function postableAccount(AccountType $type, bool $isContra): Exists
    {
        return Rule::exists('accounts', 'id')->where(fn ($query) => $query
            ->where('type', $type->value)
            ->where('is_contra', $isContra)
            ->where('is_postable', true)
            ->where('is_active', true));
    }
}
