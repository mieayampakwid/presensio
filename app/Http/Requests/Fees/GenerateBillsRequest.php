<?php

namespace App\Http\Requests\Fees;

use App\Enums\FeeKind;
use App\Models\FeeType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;

class GenerateBillsRequest extends FormRequest
{
    private const MAX_MONTHS = 24;

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
     * @return array<string, array<int, ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        $isOneTime = FeeType::query()->whereKey($this->input('fee_type_id'))->first()?->type === FeeKind::OneTime;

        return [
            'fee_type_id' => ['required', 'integer', Rule::exists('fee_types', 'id')->where('is_active', true)],
            'target' => ['required', Rule::in(['class', 'grade_level', 'all'])],
            'class_id' => ['exclude_unless:target,class', 'required', 'integer', 'exists:classes,id'],
            'grade_level' => ['exclude_unless:target,grade_level', 'required', 'integer', 'between:1,12'],
            'amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'from_month' => [Rule::requiredIf(! $isOneTime), 'nullable', 'date_format:Y-m'],
            'to_month' => [
                Rule::requiredIf(! $isOneTime),
                'nullable',
                'date_format:Y-m',
                'after_or_equal:from_month',
                function (string $attribute, mixed $value, Closure $fail) {
                    $from = Date::parse($this->input('from_month').'-01');

                    if ($from->diffInMonths(Date::parse($value.'-01')) >= self::MAX_MONTHS) {
                        $fail('Rentang bulan maksimal '.self::MAX_MONTHS.' bulan.');
                    }
                },
            ],
            'due_day' => [Rule::requiredIf(! $isOneTime), 'nullable', 'integer', 'between:1,31'],
            'due_date' => [Rule::requiredIf($isOneTime), 'nullable', 'date'],
        ];
    }
}
