<?php

namespace App\Http\Requests\AcademicYears;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends FormRequest
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
        /** @var AcademicYear|null $year */
        $year = $this->route('academic_year');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique(AcademicYear::class)->ignore($year)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
