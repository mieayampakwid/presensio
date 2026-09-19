<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_users_can_authenticate_with_username_and_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deactivated_account_status_is_not_revealed_with_a_wrong_password(): void
    {
        $user = User::factory()->inactive()->create();

        // A wrong password gets the generic credentials error — the
        // deactivation message is only visible with valid credentials,
        // so account status cannot be probed without them.
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors([
            'username' => 'These credentials do not match our records.',
        ]);

        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge(): void
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited(): void
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [Str::lower($user->username), '127.0.0.1'])), amount: 5);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
