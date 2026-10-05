<?php

namespace App\Models;

use App\Enums\EmployeeAttendanceStatus;
use App\Enums\ScanMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\EmployeeAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property EmployeeAttendanceStatus $status
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property int $late_minutes
 * @property int $early_leave_minutes
 * @property ScanMethod|null $scan_method
 * @property int|null $override_by_user_id
 * @property Carbon|null $overridden_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $overrideBy
 */
#[Fillable([
    'employee_id',
    'date',
    'status',
    'checked_in_at',
    'checked_out_at',
    'late_minutes',
    'early_leave_minutes',
    'scan_method',
    'override_by_user_id',
    'overridden_at',
    'notes',
])]
class EmployeeAttendance extends Model
{
    use Auditable;

    /** @use HasFactory<EmployeeAttendanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => EmployeeAttendanceStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'scan_method' => ScanMethod::class,
            'overridden_at' => 'datetime',
        ];
    }

    /**
     * Employee this record belongs to.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * User who manually overrode this record, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }
}
