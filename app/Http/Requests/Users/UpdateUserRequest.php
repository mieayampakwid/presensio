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
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'username' => ['required', 'string', 'max:255', Rule::unique(User::class)->ignore($target)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)->ignore($target)],
            'roles' => ['required_without:role', 'array', 'min:1'],
            'roles.*' => [Rule::enum(UserRole::class)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'profile_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Cross-field guards: student exclusivity, last-admin protection, self-lockout,
     * and optional profile link validation.
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
                $target = $this->route('user');

                if (! $target instanceof User) {
                    return;
                }

                $roles = (array) $this->input('roles', []);
                $isTargetActiveAdmin = $target->is_active && $target->hasRole(UserRole::Admin);
                $isRemovingAdmin = ! in_array(UserRole::Admin->value, $roles, true);
                $isDeactivating = $this->boolean('is_active') === false;

                if ($isTargetActiveAdmin && ($isRemovingAdmin || $isDeactivating)) {
                    $activeAdminCount = User::query()
                        ->where('is_active', true)
                        ->where(function ($query) {
                            $query->whereHas('roleGrants', fn ($q) => $q->where('role', UserRole::Admin->value))
                                ->orWhere('role', UserRole::Admin->value);
                        })
                        ->count();

                    if ($activeAdminCount <= 1) {
                        $validator->errors()->add('roles', 'The system must have at least one active administrator.');
                        $validator->errors()->add('role', 'The system must have at least one active administrator.');
                        if ($isDeactivating) {
                            $validator->errors()->add('is_active', 'The system must have at least one active administrator.');
                        }
                    }
                }

                if ($target->is($this->user())) {
                    $currentRoles = $target->roles()->map(fn ($r) => $r->value)->all();
                    sort($currentRoles);
                    $submittedRoles = array_values(array_unique($roles));
                    sort($submittedRoles);

                    if ($currentRoles !== $submittedRoles) {
                        $validator->errors()->add('role', 'You cannot change your own role.');
                        $validator->errors()->add('roles', 'You cannot change your own role.');
                    }

                    if ($isDeactivating) {
                        $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                    }
                }
            },
            function (Validator $validator): void {
                $this->linker->validateSelection(
                    $validator,
                    $this->string('role')->toString() ?: null,
                    $this->filled('profile_id') ? (int) $this->input('profile_id') : null,
                    $this->route('user'),
                );
            },
        ];
    }
}
