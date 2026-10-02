<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveRoleSwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_switch_to_a_role_they_hold(): void
    {
        $user = User::factory()->teacher()->create();
        $user->roleGrants()->create(['role' => UserRole::Parent]);

        $response = $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('active-role.switch'), [
                'role' => UserRole::Parent->value,
            ]);

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('active_role', UserRole::Parent->value);
    }

    public function test_user_cannot_switch_to_a_role_they_do_not_hold(): void
    {
        $user = User::factory()->teacher()->create();

        $response = $this->actingAs($user)
            ->post(route('active-role.switch'), [
                'role' => UserRole::Admin->value,
            ]);

        $response->assertForbidden();
    }

    public function test_switching_to_invalid_role_fails_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('active-role.switch'), [
                'role' => 'invalid_role',
            ]);

        $response->assertSessionHasErrors('role');
    }
}
