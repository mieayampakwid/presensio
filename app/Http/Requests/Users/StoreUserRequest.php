<?php

namespace App\Http\Requests\Users;

use App\Concerns\PasswordValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserProfileLinker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class StoreUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function __construct(private readonly UserProfileLinker $linker)
    {
        parent::__construct();
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('role') && ! $this->has('roles')) {
            $this->merge(['roles' => [$this->input('role')]]);
        } elseif ($this->has('roles') && ! $this->has('role')) {
            $roles = (array) $this->input('roles');
            $this->merge(['role' => $roles[0] ?? null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|Enum|Password|Unique|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)],
            'roles' => ['required_without:role', 'array', 'min:1'],
            'roles.*' => [Rule::enum(UserRole::class)],
            'role' => ['required_without:roles', Rule::enum(UserRole::class)],
            'password' => $this->passwordRules(),
            'profile_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Cross-field guards: student exclusivity and optional profile link.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $roles = (array) $this->input('roles', []);
                if (in_array(UserRole::Student->value, $roles, true) && count(array_unique($roles)) > 1) {
                    $validator->errors()->add('roles', 'The student role cannot be combined with other roles.');
                }
            },
            function (Validator $validator): void {
                $this->linker->validateSelection(
                    $validator,
                    $this->string('role')->toString() ?: null,
                    $this->filled('profile_id') ? (int) $this->input('profile_id') : null,
                    null,
                );
            },
        ];
    }
}
