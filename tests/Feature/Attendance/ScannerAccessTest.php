<?php

namespace Tests\Feature\Attendance;

use App\Enums\Ability;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScannerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_provision_a_dedicated_operator(): void
    {
        $this->actingAs(User::factory()->admin()->create())->post('/users', [
            'username' => 'gate-operator', 'roles' => ['scanner_operator'],
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!',
        ])->assertSessionHasNoErrors();
        $operator = User::where('username', 'gate-operator')->sole();
        $this->assertSame(['scanner_operator'], $operator->roles()->map(fn ($role) => $role->value)->all());
    }

    #[DataProvider('otherRoles')]
    public function test_operator_role_is_exclusive_on_create_and_update(string $other): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        $payload = ['username' => 'mixed', 'roles' => ['scanner_operator', $other], 'is_active' => true,
            'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!'];
        $this->actingAs($admin)->post('/users', $payload)->assertSessionHasErrors('roles');
        $this->put('/users/'.$operator->id, $payload)->assertSessionHasErrors('roles');
        $this->assertSame(['scanner_operator'], $operator->fresh()->roles()->map(fn ($r) => $r->value)->all());
        $this->assertDatabaseMissing('users', ['username' => 'mixed']);
    }

    public static function otherRoles(): array
    {
        return array_map(fn ($role) => [$role], ['admin', 'principal', 'teacher', 'counselor', 'finance', 'staff', 'parent', 'student']);
    }

    public function test_operator_product_boundary_and_minimal_shared_props(): void
    {
        $this->withoutVite();
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        Employee::factory()->create(['user_id' => $operator->id]);
        $operator->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test', 'dedupe_key' => 'operator-private-test', 'data' => ['secret' => 'not-for-gate'], 'read_at' => null]);
        $this->actingAs($operator)->get('/dashboard')->assertRedirect('/scanner');
        foreach (['/students', '/attendance', '/settings/profile', '/settings/appearance', '/notifications', '/announcements', '/my-leave', '/my-qr', '/my-attendance'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/settings/password', [])->assertForbidden();
        $this->post('/notifications/read-all')->assertForbidden();
        $this->post('/my-leave', [])->assertForbidden();
        $this->get('/scanner')->assertInertia(fn (Assert $page) => $page->component('scanner/index', false)
            ->missing('notifications')->missing('incomplete_class_count')
            ->where('auth.user.roles', ['scanner_operator']));
        $this->assertDatabaseCount('scan_events', 0);
        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_operator_login_discards_stale_intended_url(): void
    {
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        $this->withSession(['url.intended' => '/students'])->post(route('login.store'), [
            'username' => $operator->username, 'password' => 'password',
        ])->assertRedirect('/scanner');
    }

    public function test_login_completion_contracts_preserve_operator_landing(): void
    {
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        $this->actingAs($operator);
        foreach ([TwoFactorLoginResponse::class, PasskeyLoginResponse::class] as $contract) {
            session(['url.intended' => '/settings/profile']);
            $request = Request::create('/login', 'POST');
            $request->setLaravelSession(session()->driver());
            $request->setUserResolver(fn () => $operator);
            $this->assertSame(route('scanner.index'), app($contract)->toResponse($request)->getTargetUrl());
        }
    }

    public function test_scanner_ability_matrix_and_guest_denial(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->withRoles($role)->create();
            $allowed = in_array($role->value, ['admin', 'teacher', 'scanner_operator'], true);
            $this->assertSame($allowed, $user->can(Ability::OperateScanner->value));
        }
        $this->get('/scanner')->assertRedirect(route('login'));
        $this->postJson('/scanner/scan', ['credential' => 'x'])->assertUnauthorized();
    }

    public function test_inactive_and_revoked_operator_cannot_scan(): void
    {
        $operator = User::factory()->withRoles(UserRole::ScannerOperator)->create();
        $this->actingAs($operator);
        $operator->update(['is_active' => false]);
        $this->postJson('/scanner/scan', ['credential' => 'x'])->assertUnauthorized();
        $operator->update(['is_active' => true]);
        $operator->roleGrants()->update(['role' => 'parent']);
        $this->actingAs($operator->fresh())->postJson('/scanner/scan', ['credential' => 'x'])->assertForbidden();
        $this->assertDatabaseCount('scan_events', 0);
    }
}
