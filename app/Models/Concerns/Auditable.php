<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->orderByDesc('created_at');
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function recordAudit(string $action, ?array $old = null, ?array $new = null, ?string $reason = null): AuditLog
    {
        return app(AuditLogger::class)->record($this, $action, $old, $new, $reason);
    }
}
