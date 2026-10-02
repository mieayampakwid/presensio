<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserRoleGrant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserRolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_exactly_one_grant_per_user_equal_to_their_legacy_role(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function ($table): void {
                $table->string('role', 30)->nullable();
            });
        }

        // Create users with various legacy roles
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $parent = User::factory()->parent()->create();

        DB::table('users')->where('id', $admin->id)->update(['role' => UserRole::Admin->value]);
        DB::table('users')->where('id', $teacher->id)->update(['role' => UserRole::Teacher->value]);
        DB::table('users')->where('id', $student->id)->update(['role' => UserRole::Student->value]);
        DB::table('users')->where('id', $parent->id)->update(['role' => UserRole::Parent->value]);

        // Clear user_roles to simulate pre-migration state where users exist but user_roles is empty
        DB::table('user_roles')->truncate();
        $this->assertSame(0, DB::table('user_roles')->count());

        // Run the migration backfill method
        $migration = require database_path('migrations/2026_10_02_000001_create_user_roles_table.php');
        $migration::backfill();

        // Assert exactly one grant per user matching their users.role
        $this->assertSame(4, DB::table('user_roles')->count());

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $admin->id,
            'role' => UserRole::Admin->value,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $teacher->id,
            'role' => UserRole::Teacher->value,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $student->id,
            'role' => UserRole::Student->value,
        ]);
        $this->assertDatabaseHas('user_roles', [
            'user_id' => $parent->id,
            'role' => UserRole::Parent->value,
        ]);

        $this->assertSame(1, UserRoleGrant::where('user_id', $admin->id)->count());
        $this->assertSame(1, UserRoleGrant::where('user_id', $teacher->id)->count());
        $this->assertSame(1, UserRoleGrant::where('user_id', $student->id)->count());
        $this->assertSame(1, UserRoleGrant::where('user_id', $parent->id)->count());
    }
}
