<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_users_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee(User::latest('id')->first()->username);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_are_forbidden_from_user_management(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'username' => '199905012005011001',
            'email' => 'teacher@school.test',
            'role' => UserRole::Teacher->value,
            'password' => 'S3cure-p4ss!',
            'password_confirmation' => 'S3cure-p4ss!',
        ]);

        $response->assertSessionHasNoErrors();

        $user = User::where('username', '199905012005011001')->first();

        $this->assertNotNull($user);
        $this->assertSame(UserRole::Teacher, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('S3cure-p4ss!', $user->password));
    }

    public function test_user_creation_requires_username_role_and_password(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [])
            ->assertSessionHasErrors(['username', 'role', 'password']);
    }

    public function test_user_creation_rejects_a_duplicate_username(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => $existing->username,
                'role' => UserRole::Student->value,
                'password' => 'S3cure-p4ss!',
            ])
            ->assertSessionHasErrors('username');
    }

    public function test_admin_can_update_role_and_deactivate_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $user), [
                'username' => $user->username,
                'email' => $user->email,
                'role' => UserRole::Parent->value,
                'is_active' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Parent, $user->fresh()->role);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => UserRole::Teacher->value,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->put(route('users.update', $admin), [
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => UserRole::Admin->value,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');
    }

    public function test_admin_can_reset_a_users_password(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('users.password.update', $user), [
                'password' => 'N3w-p4ss!',
                'password_confirmation' => 'N3w-p4ss!',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('N3w-p4ss!', $user->fresh()->password));
    }

    public function test_password_reset_is_throttled(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        foreach (range(1, 6) as $i) {
            $this->actingAs($admin)
                ->put(route('users.password.update', $user), [
                    'password' => 'N3w-p4ss!',
                    'password_confirmation' => 'N3w-p4ss!',
                ]);
        }

        // 7th request within the window hits throttle:6,1
        $this->actingAs($admin)
            ->put(route('users.password.update', $user), [
                'password' => 'N3w-p4ss!',
                'password_confirmation' => 'N3w-p4ss!',
            ])
            ->assertTooManyRequests();
    }

    public static function nonAdminRoles(): array
    {
        return [
            'teacher' => ['teacher'],
            'student' => ['student'],
            'parent' => ['parent'],
        ];
    }
}
