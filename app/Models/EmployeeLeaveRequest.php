<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveType;
use Database\Factories\EmployeeLeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee-submitted absence request covering one date range (spec 16
 * §Schema 4). Approval upserts attendance rows for the expected days in
 * the range; the employee may cancel while still pending.
 *
 * @property int $id
 * @property int $employee_id
 * @property LeaveType $type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $reason
 * @property string|null $attachment_path
 * @property LeaveRequestStatus $status
 * @property string|null $review_note
 * @property int|null $reviewed_by_user_id
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $reviewedBy
 */
#[Fillable([
    'employee_id',
    'type',
    'start_date',
    'end_date',
    'reason',
    'attachment_path',
    'status',
    'review_note',
    'reviewed_by_user_id',
    'reviewed_at',
])]
class EmployeeLeaveRequest extends Model
{
    /** @use HasFactory<EmployeeLeaveRequestFactory> */
    use HasFactory;

    /**
     * The employee who submitted this request.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Principal or admin who approved or rejected the request.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveType::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => LeaveRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Scope requests to one employee.
     *
     * @param  Builder<EmployeeLeaveRequest>  $query
     */
    public function scopeForEmployee(Builder $query, int $employeeId): void
    {
        $query->where('employee_id', $employeeId);
    }

    /**
     * Scope requests that still block new submissions: pending or approved.
     * Rejected and cancelled ranges may be resubmitted.
     *
     * @param  Builder<EmployeeLeaveRequest>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value]);
    }

    /**
     * Scope requests whose date range overlaps the given inclusive range.
     *
     * @param  Builder<EmployeeLeaveRequest>  $query
     */
    public function scopeOverlapping(Builder $query, string $startDate, string $endDate): void
    {
        $query->where('start_date', '<=', $endDate)->where('end_date', '>=', $startDate);
    }
}
