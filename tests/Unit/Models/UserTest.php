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

    public function test_role_accessor_returns_active_role(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue($user->hasRole(UserRole::Admin));
    }

    public function test_factory_defaults_to_an_active_teacher(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Teacher, $user->role);
        $this->assertTrue($user->hasRole(UserRole::Teacher));
        $this->assertTrue($user->is_active);
    }

    #[DataProvider('roles')]
    public function test_factory_role_states_set_the_role(UserRole $role, string $state): void
    {
        $user = User::factory()->{$state}()->create();

        $this->assertSame($role, $user->role);
        $this->assertTrue($user->hasRole($role));
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
}
