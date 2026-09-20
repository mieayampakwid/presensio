<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\ScanMethod;
use App\Observers\AttendanceObserver;
use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The mutable per-day attendance projection (spec 03 §Schema). The
 * append-only scan_events table holds the attempt history.
 *
 * @property int $id
 * @property int $student_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property ScanMethod|null $scan_method
 * @property int|null $override_by_user_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'student_id',
    'date',
    'status',
    'checked_in_at',
    'checked_out_at',
    'scan_method',
    'override_by_user_id',
    'notes',
])]
#[ObservedBy(AttendanceObserver::class)]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    /**
     * Student this record belongs to.
     *
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Admin/teacher who last manually overrode this record.
     *
     * @return BelongsTo<User, $this>
     */
    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => AttendanceStatus::class,
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'scan_method' => ScanMethod::class,
        ];
    }
}
