<?php

namespace App\Http\Requests\Staff;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveRequest extends FormRequest
{
    /**
     * Reviewers are admins and principals (spec 16 §Req 5); the route
     * middleware enforces this too — this is defense in depth.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(UserRole::Admin, UserRole::Principal) ?? false;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
