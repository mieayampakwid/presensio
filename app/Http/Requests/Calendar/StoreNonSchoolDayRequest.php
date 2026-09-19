<?php

namespace App\Http\Requests\Calendar;

use App\Enums\UserRole;
use App\Models\NonSchoolDay;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonSchoolDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * One row per date — sync and manual collide by design; the unique
     * rule makes the collision explicit instead of silent.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date', Rule::unique(NonSchoolDay::class, 'date')],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
