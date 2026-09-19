<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth', 'role:admin'])->get('/_test/admin-only', fn () => 'ok');
        Route::middleware(['auth', 'role:teacher,admin'])->get('/_test/staff-only', fn () => 'ok');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/_test/admin-only')->assertRedirect(route('login'));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_receive_403(string $factoryState): void
    {
        $user = User::factory()->{$factoryState}()->create();

        $this->actingAs($user)
            ->get('/_test/admin-only')
            ->assertForbidden();
    }

    public function test_admin_is_allowed_through(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/_test/admin-only')
            ->assertOk();
    }

    public function test_middleware_accepts_multiple_roles(): void
    {
        $teacher = User::factory()->create();

        $this->actingAs($teacher)
            ->get('/_test/staff-only')
            ->assertOk();
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
