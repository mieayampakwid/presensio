<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->roleGrants()->doesntExist()) {
                $user->roleGrants()->create([
                    'role' => UserRole::Teacher,
                    'created_at' => now(),
                ]);
                $user->unsetRelation('roleGrants');
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the user has the given roles.
     */
    public function withRoles(UserRole ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            $user->roleGrants()->delete();
            foreach ($roles as $role) {
                $user->roleGrants()->create([
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }
            $user->unsetRelation('roleGrants');
        });
    }

    /**
     * Indicate that the user is an administrator.
     */
    public function admin(): static
    {
        return $this->withRoles(UserRole::Admin);
    }

    /**
     * Indicate that the user is a principal.
     */
    public function principal(): static
    {
        return $this->withRoles(UserRole::Principal);
    }

    /**
     * Indicate that the user is a teacher.
     */
    public function teacher(): static
    {
        return $this->withRoles(UserRole::Teacher);
    }

    /**
     * Indicate that the user is a counselor.
     */
    public function counselor(): static
    {
        return $this->withRoles(UserRole::Counselor);
    }

    /**
     * Indicate that the user is a finance officer.
     */
    public function finance(): static
    {
        return $this->withRoles(UserRole::Finance);
    }

    /**
     * Indicate that the user is a staff member.
     */
    public function staff(): static
    {
        return $this->withRoles(UserRole::Staff);
    }

    /**
     * Indicate that the user is a student.
     */
    public function student(): static
    {
        return $this->withRoles(UserRole::Student);
    }

    /**
     * Indicate that the user is a parent.
     */
    public function parent(): static
    {
        return $this->withRoles(UserRole::Parent);
    }

    /**
     * Indicate that the account has been deactivated.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
