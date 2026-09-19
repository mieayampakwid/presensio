<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserProfileLinker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');

        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class)->ignore($target)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)->ignore($target)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'profile_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Cross-field guards: an admin cannot edit their own role or deactivate
     * their own account (prevents admin lockout); the optional profile link
     * must match the selected role's table and be linkable.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');

                if (! $target instanceof User || ! $target->is($this->user())) {
                    return;
                }

                if ($this->string('role')->toString() !== $this->user()->role->value) {
                    $validator->errors()->add('role', 'You cannot change your own role.');
                }

                if ($this->boolean('is_active') === false) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }
            },
            function (Validator $validator) {
                app(UserProfileLinker::class)->validateSelection(
                    $validator,
                    $this->string('role')->toString(),
                    $this->filled('profile_id') ? (int) $this->input('profile_id') : null,
                    $this->route('user'),
                );
            },
        ];
    }
}
