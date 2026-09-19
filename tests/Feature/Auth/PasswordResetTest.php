<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_password_link_can_be_requested_with_a_username(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['username' => $user->username]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_usernames_get_the_same_success_response(): void
    {
        $this->post(route('password.email'), ['username' => 'no-such-user'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('passwords.sent'));
    }

    public function test_user_without_email_receives_no_mail_but_success_status(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => null]);

        $this->post(route('password.email'), ['username' => $user->username]);

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $this->get(route('password.reset', ['token' => 'token', 'username' => '123456']))
            ->assertOk();
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['username' => $user->username]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'username' => $user->username,
                'password' => 'S3cure-p4ss!',
                'password_confirmation' => 'S3cure-p4ss!',
            ]);

            $response->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('S3cure-p4ss!', $user->fresh()->password));
    }

    public function test_password_cannot_be_reset_with_an_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'username' => $user->username,
            'password' => 'S3cure-p4ss!',
            'password_confirmation' => 'S3cure-p4ss!',
        ])->assertSessionHasErrors('username');
    }

    public function test_reset_password_request_is_throttled(): void
    {
        foreach (range(1, 7) as $i) {
            $this->post(route('password.email'), ['username' => 'anyone']);
        }

        // 7th+ request within the window hits throttle:6,1
        $this->post(route('password.email'), ['username' => 'anyone'])
            ->assertTooManyRequests();
    }
}
