<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

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
            'principal' => [UserRole::Principal, 'principal'],
            'teacher' => [UserRole::Teacher, 'teacher'],
            'counselor' => [UserRole::Counselor, 'counselor'],
            'finance' => [UserRole::Finance, 'finance'],
            'staff' => [UserRole::Staff, 'staff'],
            'parent' => [UserRole::Parent, 'parent'],
            'student' => [UserRole::Student, 'student'],
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

    public function test_roles_returns_collection_ordered_by_enum_definition(): void
    {
        $user = User::factory()->withRoles(UserRole::Parent, UserRole::Teacher, UserRole::Principal)->create();

        $roles = $user->roles();

        // Enum order: Admin, Principal, Teacher, Counselor, Finance, Staff, Parent, Student
        $this->assertEquals([
            UserRole::Principal,
            UserRole::Teacher,
            UserRole::Parent,
        ], $roles->all());
    }

    public function test_has_role_and_has_any_role_check_held_roles(): void
    {
        $user = User::factory()->withRoles(UserRole::Teacher, UserRole::Parent)->create();

        $this->assertTrue($user->hasRole(UserRole::Teacher));
        $this->assertTrue($user->hasRole(UserRole::Parent));
        $this->assertFalse($user->hasRole(UserRole::Admin));
        $this->assertFalse($user->hasRole(UserRole::Student));

        $this->assertTrue($user->hasAnyRole(UserRole::Admin, UserRole::Teacher));
        $this->assertTrue($user->hasAnyRole(UserRole::Parent, UserRole::Counselor));
        $this->assertFalse($user->hasAnyRole(UserRole::Admin, UserRole::Student));
    }

    public function test_active_role_returns_session_role_if_held(): void
    {
        $user = User::factory()->withRoles(UserRole::Teacher, UserRole::Parent)->create();

        session(['active_role' => UserRole::Parent->value]);

        $this->assertSame(UserRole::Parent, $user->activeRole());
    }

    public function test_active_role_falls_back_to_first_held_role_when_session_role_is_not_held(): void
    {
        $user = User::factory()->withRoles(UserRole::Teacher, UserRole::Parent)->create();

        session(['active_role' => UserRole::Admin->value]);

        // First held role in enum order is Teacher
        $this->assertSame(UserRole::Teacher, $user->activeRole());
    }

    public function test_active_role_defaults_to_first_held_role_when_no_session(): void
    {
        $user = User::factory()->withRoles(UserRole::Parent, UserRole::Counselor)->create();

        session()->forget('active_role');

        // Enum order: Counselor comes before Parent
        $this->assertSame(UserRole::Counselor, $user->activeRole());
    }
}
