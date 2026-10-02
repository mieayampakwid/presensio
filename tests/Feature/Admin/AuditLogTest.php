<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_principal_can_view_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $principal = User::factory()->principal()->create();

        AuditLog::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-logs/index')
                ->has('logs.data', 3));

        $this->actingAs($principal)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-logs/index')
                ->has('logs.data', 3));
    }

    public function test_unauthorized_roles_cannot_view_audit_logs(): void
    {
        $finance = User::factory()->finance()->create();
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($finance)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($teacher)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_filters_narrow_audit_log_results(): void
    {
        $admin = User::factory()->admin()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $targetUser = User::factory()->create();

        AuditLog::factory()->create([
            'user_id' => $user1->id,
            'auditable_type' => User::class,
            'auditable_id' => $targetUser->id,
            'action' => 'roles_updated',
            'created_at' => '2026-10-01 10:00:00',
        ]);

        AuditLog::factory()->create([
            'user_id' => $user2->id,
            'auditable_type' => 'App\Models\SchoolClass',
            'auditable_id' => 99,
            'action' => 'created',
            'created_at' => '2026-10-02 10:00:00',
        ]);

        // Filter by user_id
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['user_id' => $user1->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.user_id', $user1->id));

        // Filter by auditable_type and auditable_id
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', [
                'auditable_type' => User::class,
                'auditable_id' => $targetUser->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.auditable_id', $targetUser->id));

        // Filter by date range
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', [
                'from' => '2026-10-02',
                'to' => '2026-10-02',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.user_id', $user2->id));
    }
}
