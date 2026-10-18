<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string $default_passing_threshold
 * @property array<string, array{whatsapp: bool, email: bool}>|null $notification_channels
 * @property int|null $whatsapp_daily_quota
 * @property string $quiet_hours_start
 * @property string $quiet_hours_end
 * @property int $bill_reminder_days_before
 * @property string $staff_start_time
 * @property string $staff_end_time
 * @property string $staff_absent_sweep_time
 * @property int $fiscal_year_start_month
 * @property int|null $prepaid_revenue_account_id
 * @property int|null $bad_debt_expense_account_id
 * @property int|null $net_assets_closing_account_id
 * @property int|null $default_transfer_account_id
 * @property string $default_scope_weight
 * @property string $default_final_weight
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account|null $prepaidRevenueAccount
 * @property-read Account|null $badDebtExpenseAccount
 * @property-read Account|null $netAssetsClosingAccount
 * @property-read Account|null $defaultTransferAccount
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
    'default_passing_threshold',
    'notification_channels',
    'whatsapp_daily_quota',
    'quiet_hours_start',
    'quiet_hours_end',
    'bill_reminder_days_before',
    'staff_start_time',
    'staff_end_time',
    'staff_absent_sweep_time',
    'fiscal_year_start_month',
    'prepaid_revenue_account_id',
    'bad_debt_expense_account_id',
    'net_assets_closing_account_id',
    'default_transfer_account_id',
    'default_scope_weight',
    'default_final_weight',
])]
class SchoolSetting extends Model
{
    protected $table = 'settings';

    /**
     * @return BelongsTo<Account, $this>
     */
    public function prepaidRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'prepaid_revenue_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function badDebtExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bad_debt_expense_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function netAssetsClosingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'net_assets_closing_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function defaultTransferAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_transfer_account_id');
    }

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
            'default_passing_threshold' => 'decimal:2',
            'notification_channels' => 'array',
            'whatsapp_daily_quota' => 'integer',
            'bill_reminder_days_before' => 'integer',
            'fiscal_year_start_month' => 'integer',
            'prepaid_revenue_account_id' => 'integer',
            'bad_debt_expense_account_id' => 'integer',
            'net_assets_closing_account_id' => 'integer',
            'default_transfer_account_id' => 'integer',
            'default_scope_weight' => 'decimal:2',
            'default_final_weight' => 'decimal:2',
        ];
    }
}
