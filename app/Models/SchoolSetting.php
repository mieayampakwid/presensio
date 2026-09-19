<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row settings record (id = 1) anchoring attendance business logic
 * (spec 03 §Decisions "Application Configurability").
 *
 * @property int $id
 * @property string $school_timezone
 * @property string $school_start_time
 * @property bool $require_checkout
 * @property string $auto_absent_cron_time
 * @property int $scan_debounce_minutes
 * @property int $scan_drift_tolerance_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'school_timezone',
    'school_start_time',
    'require_checkout',
    'auto_absent_cron_time',
    'scan_debounce_minutes',
    'scan_drift_tolerance_minutes',
])]
class SchoolSetting extends Model
{
    protected $table = 'settings';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'require_checkout' => 'boolean',
            'scan_debounce_minutes' => 'integer',
            'scan_drift_tolerance_minutes' => 'integer',
        ];
    }
}
