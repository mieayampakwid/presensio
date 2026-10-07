<?php

namespace App\Http\Requests\Finance;

use App\Enums\AccountType;
use App\Enums\NetAssetRestriction;
use App\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Account|null $account */
        $account = $this->route('account');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('accounts', 'code')->ignore($account?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'is_contra' => ['sometimes', 'boolean'],
            'restriction' => [
                'nullable',
                Rule::enum(NetAssetRestriction::class),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:accounts,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($account) {
                    if ($account && (int) $value === $account->id) {
                        $fail('Akun tidak dapat menjadi induk bagi dirinya sendiri.');
                    }
                },
            ],
            'is_postable' => ['sometimes', 'boolean'],
            'is_cash' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Account|null $account */
            $account = $this->route('account');
            $type = $this->input('type');

            if ($type !== AccountType::NetAssets->value && $this->filled('restriction')) {
                $validator->errors()->add('restriction', 'Pembatasan hanya berlaku untuk akun Aset Neto.');
            }

            if ($this->filled('parent_id')) {
                $parentId = (int) $this->input('parent_id');
                $parent = Account::query()->find($parentId);
                if ($parent !== null && $parent->hasPostedLines()) {
                    $validator->errors()->add('parent_id', 'Akun yang telah memiliki transaksi posted tidak dapat dijadikan akun induk.');
                }
            }

            if ($account !== null && $account->hasPostedLines()) {
                if ($type !== $account->type->value) {
                    $validator->errors()->add('type', 'Tipe akun tidak dapat diubah setelah memiliki transaksi posted.');
                }
                if ($this->input('code') !== $account->code) {
                    $validator->errors()->add('code', 'Kode akun tidak dapat diubah setelah memiliki transaksi posted.');
                }
                if ($this->boolean('is_cash') !== $account->is_cash) {
                    $validator->errors()->add('is_cash', 'Status kas/bank tidak dapat diubah setelah memiliki transaksi posted.');
                }
                if ($this->boolean('is_postable') !== $account->is_postable) {
                    $validator->errors()->add('is_postable', 'Status postable tidak dapat diubah setelah memiliki transaksi posted.');
                }
                if ($this->input('parent_id') != $account->parent_id) {
                    $validator->errors()->add('parent_id', 'Akun induk tidak dapat diubah setelah memiliki transaksi posted.');
                }
            }

            if ($this->boolean('is_postable') && $account !== null && $account->children()->exists()) {
                $validator->errors()->add('is_postable', 'Akun induk dengan sub-akun tidak dapat menjadi akun postable.');
            }
        });
    }

    /**
     * Get sanitized attributes for persistence.
     *
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        $data = $this->validated();

        if (($data['type'] ?? null) !== AccountType::NetAssets->value) {
            $data['restriction'] = null;
        }

        $data['is_contra'] = $this->boolean('is_contra');
        $data['is_postable'] = $this->has('is_postable') ? $this->boolean('is_postable') : true;
        $data['is_cash'] = $this->boolean('is_cash');
        $data['is_active'] = $this->has('is_active') ? $this->boolean('is_active') : true;

        return $data;
    }
}
