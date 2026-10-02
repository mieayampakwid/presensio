<?php

namespace Tests\Unit\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_records_audit_log_with_actor_old_new_reason_and_ip(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($actor);

        $logger = new AuditLogger;
        $log = $logger->record(
            auditable: $target,
            action: 'updated',
            old: ['roles' => ['teacher']],
            new: ['roles' => ['teacher', 'parent']],
            reason: 'Promotion to coordinator',
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertSame($actor->id, $log->user_id);
        $this->assertSame('updated', $log->action);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame($target->id, $log->auditable_id);
        $this->assertSame(['roles' => ['teacher']], $log->old_values);
        $this->assertSame(['roles' => ['teacher', 'parent']], $log->new_values);
        $this->assertSame('Promotion to coordinator', $log->reason);
        $this->assertNotNull($log->created_at);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $actor->id,
            'action' => 'updated',
            'reason' => 'Promotion to coordinator',
        ]);
    }

    public function test_updating_an_audit_log_throws_logic_exception(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be updated.');

        $log->update(['reason' => 'Modified']);
    }

    public function test_deleting_an_audit_log_throws_logic_exception(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Audit logs are append-only and cannot be deleted.');

        $log->delete();
    }

    public function test_audit_failure_rolls_back_business_mutation_in_transaction(): void
    {
        $user = User::factory()->create(['username' => 'original_username']);
        $logger = new AuditLogger;

        try {
            DB::transaction(function () use ($user, $logger): void {
                $user->update(['username' => 'changed_username']);

                // Action is varchar(30); passing > 30 chars causes DB constraint violation
                $logger->record(
                    auditable: $user,
                    action: str_repeat('A', 50),
                    old: ['username' => 'original_username'],
                    new: ['username' => 'changed_username'],
                );
            });
        } catch (\Throwable) {
            // Expected exception
        }

        // Verify business mutation was rolled back
        $this->assertSame('original_username', $user->fresh()->username);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
