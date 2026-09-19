<?php

namespace App\Http\Requests\Settings;

use App\Enums\UserRole;
use App\Models\Guardian;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuardianContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request: a parent with
     * a linked guardian profile.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Parent
            && $this->user()->guardian !== null;
    }

    /**
     * Get the validation rules that apply to the request. Deliberately
     * mirrors the admin guardian FormRequests — only these three fields are
     * editable by the guardian themselves; name is admin-managed.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Guardian $guardian */
        $guardian = $this->user()->guardian;

        return [
            'phone_number' => ['required', 'string', 'max:32', Rule::unique(Guardian::class)->ignore($guardian)],
            'work' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
