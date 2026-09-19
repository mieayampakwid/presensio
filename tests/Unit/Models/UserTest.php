<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    public function test_role_is_cast_to_the_user_role_enum(): void
    {
        $user = User::factory()->make(['role' => UserRole::Admin]);

        $this->assertSame(UserRole::Admin, $user->role);
    }

    public function test_factory_defaults_to_an_active_teacher(): void
    {
        $user = User::factory()->make();

        $this->assertSame(UserRole::Teacher, $user->role);
        $this->assertTrue($user->is_active);
    }

    #[DataProvider('roles')]
    public function test_factory_role_states_set_the_role(UserRole $role, string $state): void
    {
        $user = User::factory()->{$state}()->make();

        $this->assertSame($role, $user->role);
    }

    public static function roles(): array
    {
        return [
            'admin' => [UserRole::Admin, 'admin'],
            'student' => [UserRole::Student, 'student'],
            'parent' => [UserRole::Parent, 'parent'],
        ];
    }

    public function test_inactive_state_deactivates_the_account(): void
    {
        $user = User::factory()->inactive()->make();

        $this->assertFalse($user->is_active);
    }

    public function test_password_reset_key_falls_back_to_the_username(): void
    {
        $withEmail = User::factory()->make(['email' => 'teacher@school.test']);
        $withoutEmail = User::factory()->make(['email' => null]);

        $this->assertSame('teacher@school.test', $withEmail->getEmailForPasswordReset());
        $this->assertSame($withoutEmail->username, $withoutEmail->getEmailForPasswordReset());
    }
}
