<?php

namespace App\Http\Requests\AcademicYears;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The roll-over submission (spec 07): a target year, an effective date,
 * and one mapping row per source class — map to an existing target class,
 * create a new one, or leave the students unmapped (alumni).
 */
class RollOverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_year_id' => ['required', Rule::exists(AcademicYear::class, 'id')],
            'effective_on' => ['required', 'date'],
            'mappings' => ['required', 'array'],
            'mappings.*.mode' => ['required', Rule::in(['existing', 'new', 'none'])],
            'mappings.*.target_class_id' => ['nullable', Rule::exists(SchoolClass::class, 'id')],
            'mappings.*.new_name' => ['nullable', 'string', 'max:255'],
            'mappings.*.new_teacher_id' => ['nullable', Rule::exists(Teacher::class, 'id')],
        ];
    }

    /**
     * @return array<int, array{mode: 'existing'|'new'|'none', target_class_id?: int|null, new_name?: string|null, new_teacher_id?: int|null}>
     */
    public function mappings(): array
    {
        return collect((array) $this->input('mappings', []))
            ->map(fn ($mapping) => [
                'mode' => $mapping['mode'],
                'target_class_id' => isset($mapping['target_class_id']) ? (int) $mapping['target_class_id'] : null,
                'new_name' => $mapping['new_name'] ?? null,
                'new_teacher_id' => isset($mapping['new_teacher_id']) ? (int) $mapping['new_teacher_id'] : null,
            ])
            ->all();
    }

    public function effectiveOn(): string
    {
        return (string) $this->input('effective_on');
    }
}
