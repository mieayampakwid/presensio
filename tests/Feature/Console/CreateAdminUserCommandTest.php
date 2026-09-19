<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_active_admin_user(): void
    {
        $this->artisan('app:create-admin', [
            'username' => 'admin-001',
            '--email' => 'admin@school.test',
            '--password' => 'S3cure-p4ss!',
        ])->assertSuccessful();

        $admin = User::where('username', 'admin-001')->first();

        $this->assertNotNull($admin);
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
    }

    public function test_rejects_a_duplicate_username(): void
    {
        User::factory()->create(['username' => 'admin-001']);

        $this->artisan('app:create-admin', [
            'username' => 'admin-001',
            '--password' => 'S3cure-p4ss!',
        ])->assertFailed();

        $this->assertSame(1, User::where('username', 'admin-001')->count());
    }

    public function test_rejects_a_weak_password(): void
    {
        $this->artisan('app:create-admin', [
            'username' => 'admin-001',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['username' => 'admin-001']);
    }
}
