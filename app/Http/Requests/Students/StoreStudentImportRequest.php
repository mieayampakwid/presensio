<?php

namespace App\Http\Requests\Students;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreStudentImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    /**
     * Get the validation rules that apply to the request. Legacy binary
     * .xls is rejected by the mime check; the message tells the operator
     * what to do about it.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:10240'],
            'auto_create_classes' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Imported students are enrolled into the active year, so one must exist.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (AcademicYear::active() === null) {
                    $validator->errors()->add(
                        'file',
                        'Create and activate an academic year before importing students.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'Legacy .xls files are not supported — save the sheet as .xlsx or CSV.',
        ];
    }
}
