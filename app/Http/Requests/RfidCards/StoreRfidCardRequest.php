<?php

namespace App\Http\Requests\RfidCards;

use App\Enums\UserRole;
use App\Models\RfidCard;
use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRfidCardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'rfid_number' => ['required', 'string', 'max:255', Rule::unique(RfidCard::class)],
            'student_id' => ['nullable', Rule::exists(Student::class, 'id')],
        ];
    }
}
