<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Single-row settings record (id = 1) anchoring attendance business logic
 * and school profile (spec 03 §Decisions, spec 15 §School profile).
 *
 * @property int $id
 * @property string $school_timezone
 * @property string $school_start_time
 * @property bool $require_checkout
 * @property string $auto_absent_cron_time
 * @property int $scan_debounce_minutes
 * @property int $scan_drift_tolerance_minutes
 * @property list<int> $school_operational_days
 * @property string $school_name
 * @property string|null $npsn
 * @property string|null $school_address
 * @property string|null $school_phone
 * @property string|null $school_email
 * @property string|null $logo_path
 * @property string|null $principal_name
 * @property string|null $principal_nip
 * @property string|null $bank_name
 * @property string|null $bank_account_number
 * @property string|null $bank_account_holder
 * @property string $default_curriculum
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
    'school_operational_days',
    'school_name',
    'npsn',
    'school_address',
    'school_phone',
    'school_email',
    'logo_path',
    'principal_name',
    'principal_nip',
    'bank_name',
    'bank_account_number',
    'bank_account_holder',
    'default_curriculum',
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
            'school_operational_days' => 'array',
        ];
    }
}
