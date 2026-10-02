<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_their_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('locale.update'), ['locale' => 'id'])
            ->assertRedirect()
            ->assertCookie('locale', 'id');

        $this->assertSame('id', $user->fresh()->locale);
    }

    public function test_guest_language_is_kept_in_a_cookie(): void
    {
        $this->put(route('locale.update'), ['locale' => 'id'])
            ->assertRedirect()
            ->assertCookie('locale', 'id');

        $this->withCookie('locale', 'id')
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'id')
                ->where('translations.roles.teacher', 'Guru'));
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($user->fresh()->locale);
    }

    public function test_saved_preference_wins_over_cookie_and_translates_server_messages(): void
    {
        $user = User::factory()->create(['locale' => 'id']);

        $this->actingAs($user)
            ->withCookie('locale', 'en')
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'id'));

        $this->assertSame('Guru', trans('roles.teacher'));
        $this->assertSame('Kata sandi wajib diisi.', trans('validation.required', ['attribute' => 'Kata sandi']));
    }

    public function test_default_locale_is_used_without_preference(): void
    {
        $this->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.roles.teacher', 'Teacher'));
    }

    public function test_missing_indonesian_key_falls_back_to_english(): void
    {
        $this->app['translator']->addLines(['roles.orphan' => 'English only'], 'en');

        $this->withCookie('locale', 'id')
            ->get(route('login'))
            ->assertInertia(fn (Assert $page) => $page->where('translations.roles.orphan', 'English only'));
    }
}
