<?php

namespace App\Http\Requests\Fees;

use App\Models\StudentFeeDiscount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SaveDiscountRequest extends FormRequest
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
     * @return array<string, array<int, ValidationRule|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var StudentFeeDiscount|null $discount */
        $discount = $this->route('discount');

        return [
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
                Rule::unique(StudentFeeDiscount::class)
                    ->where('fee_type_id', $this->input('fee_type_id'))
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->ignore($discount),
            ],
            'fee_type_id' => ['required', 'integer', 'exists:fee_types,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'percent' => ['nullable', 'numeric', 'between:0,100', 'required_without:fixed_amount', 'prohibits:fixed_amount'],
            'fixed_amount' => ['nullable', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_id.unique' => 'Siswa ini sudah memiliki diskon untuk jenis biaya dan tahun ajaran tersebut.',
            'percent.required_without' => 'Isi salah satu: persentase atau nominal tetap.',
            'percent.prohibits' => 'Isi persentase atau nominal tetap, tidak keduanya.',
        ];
    }
}
