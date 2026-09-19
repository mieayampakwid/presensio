<?php

namespace App\Http\Requests\Students;

use App\Enums\UserRole;
use App\Services\StudentImport\ImportOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared rules for the import preview and run steps: both receive the
 * upload token, the column mapping, and the import options.
 */
class PreviewStudentImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * Get the validation rules that apply to the request. full_name and
     * class must be mapped; every other canonical field is optional.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/^[a-f0-9]{40}$/'],
            'mapping' => ['required', 'array'],
            'mapping.full_name' => ['required', 'string'],
            'mapping.class' => ['required', 'string'],
            'mapping.*' => ['nullable', 'string'],
            'auto_create_classes' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The canonical mapping, dropping unmapped (empty) fields.
     *
     * @return array<string, string>
     */
    public function columnMapping(): array
    {
        return array_filter(
            (array) $this->input('mapping', []),
            fn ($header): bool => is_string($header) && $header !== '',
        );
    }

    public function importOptions(): ImportOptions
    {
        return new ImportOptions(
            autoCreateClasses: $this->boolean('auto_create_classes'),
        );
    }
}
